<?php

namespace App\Actions\Exclusao;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\CompartilhamentoDeModelo;
use App\Models\Contracts\Excluivel;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Exclusao;
use App\Models\GradeCurricular;
use App\Models\Importacao;
use App\Models\ModeloDocumento;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\QuadroHorario;
use App\Models\Questao;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use App\Support\PlanoDeExclusao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Manda um registro para a Lixeira, com tudo o que depende dele.
 *
 * {@see planejar()} não grava nada: diz o que vai sair e o que muda, para
 * a confirmação mostrar. {@see executar()} faz, numa transação só, e
 * guarda o lote — é por ele que a Lixeira restaura exatamente o que saiu
 * junto, e não o que já estava lá por outra exclusão.
 *
 * Nada é apagado do banco, a não ser o que não pode ficar ocupando lugar
 * (a aula do quadro de horários): esse sai de fato, e o lote guarda uma
 * cópia para recriá-lo.
 */
class ExcluirRegistroAction
{
    /** Como cada tipo aparece no resumo do impacto. */
    public const ROTULOS = [
        Eixo::class => 'Eixo(s)',
        Curso::class => 'curso(s)',
        Turma::class => 'turma(s)',
        Disciplina::class => 'disciplina(s)',
        GradeCurricular::class => 'versão(ões) de grade',
        Aluno::class => 'aluno(s)',
        Prova::class => 'prova(s)',
        SolicitacaoProva::class => 'solicitação(ões) de questões',
        Questao::class => 'questão(ões)',
        Importacao::class => 'importação(ões) de resultados',
        ResultadoAluno::class => 'resultado(s) de aluno',
        QuadroHorario::class => 'aula(s) do quadro de horários',
        ModeloProva::class => 'modelo(s) de prova',
        ModeloDocumento::class => 'modelo(s) de documento',
        CompartilhamentoDeModelo::class => 'compartilhamento(s) de modelo',
        User::class => 'usuário(s)',
    ];

    public function planejar(Model&Excluivel $raiz): PlanoDeExclusao
    {
        $vistos = [$this->chave($raiz) => true];
        $fila = [$raiz];
        $cascata = collect();
        $retirados = collect();

        while ($atual = array_shift($fila)) {
            if (! $atual instanceof Excluivel) {
                continue;
            }

            foreach ($atual->dependentesNaExclusao() as $dependente) {
                if (isset($vistos[$this->chave($dependente)])) {
                    continue;
                }

                $vistos[$this->chave($dependente)] = true;
                $cascata->push($dependente);
                $fila[] = $dependente;
            }

            foreach ($atual->retiradosNaExclusao() as $retirado) {
                if (! isset($vistos[$this->chave($retirado)])) {
                    $vistos[$this->chave($retirado)] = true;
                    $retirados->push($retirado);
                }
            }
        }

        $contagens = $cascata->concat($retirados)
            ->countBy(fn (Model $m) => self::ROTULOS[$m::class] ?? class_basename($m))
            // Na ordem em que os tipos aparecem em ROTULOS: do maior para o menor.
            ->sortBy(fn (int $n, string $rotulo) => array_search($rotulo, array_values(self::ROTULOS), true))
            ->all();

        return new PlanoDeExclusao(
            $raiz->rotuloParaExclusao(),
            $cascata,
            $retirados,
            $contagens,
            $raiz->avisosDaExclusao(),
            $raiz->confirmacoesParaExcluir(),
            $raiz->impedimentoDaExclusao(),
        );
    }

    public function executar(Model&Excluivel $raiz, User $autor): Exclusao
    {
        Gate::forUser($autor)->authorize('delete', $raiz);

        $plano = $this->planejar($raiz);

        if ($plano->impedimento !== null) {
            throw RegraDeNegocioException::porque($plano->impedimento);
        }

        // O caminho até o Eixo é lido antes: depois, ele pode estar na Lixeira junto.
        $eixoId = method_exists($raiz, 'eixoId') ? $raiz->eixoId() : null;

        return DB::transaction(function () use ($raiz, $autor, $plano, $eixoId) {
            $lote = Exclusao::query()->create([
                'registro_type' => $raiz::class,
                'registro_id' => $raiz->getKey(),
                'rotulo' => $plano->rotulo,
                'eixo_id' => $eixoId,
                // Lista de pares, e não objeto: o MySQL reordena as chaves de um objeto JSON,
                // e o resumo da Lixeira sairia embaralhado.
                'resumo' => [
                    'contagens' => collect($plano->contagens)->map(fn (int $quantos, string $rotulo) => compact('rotulo', 'quantos'))->values()->all(),
                    'avisos' => $plano->avisos,
                ],
                'excluido_por' => $autor->getKey(),
            ]);

            foreach ($plano->retirados as $retirado) {
                $lote->itens()->create([
                    'registro_type' => $retirado::class,
                    'registro_id' => $retirado->getKey(),
                    'dados' => $retirado->getRawOriginal(),
                ]);

                $retirado->delete();
            }

            foreach ([...$plano->cascata, $raiz] as $registro) {
                if (! in_array(SoftDeletes::class, class_uses_recursive($registro), true)) {
                    throw new LogicException($registro::class.' sai em cascata mas não tem exclusão lógica.');
                }

                $lote->itens()->create([
                    'registro_type' => $registro::class,
                    'registro_id' => $registro->getKey(),
                ]);

                $registro->delete();
            }

            activity('exclusao')
                ->performedOn($raiz)
                ->causedBy($autor)
                ->withProperties(['lote' => $lote->getKey(), 'contagens' => $plano->contagens])
                ->log("Excluído: {$plano->rotulo}");

            return $lote;
        });
    }

    private function chave(Model $registro): string
    {
        return $registro::class.'#'.$registro->getKey();
    }
}
