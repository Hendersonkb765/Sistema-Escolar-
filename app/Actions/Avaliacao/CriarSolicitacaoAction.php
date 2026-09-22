<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Disciplina;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Abre uma solicitação de questões a um professor.
 *
 * Cada questão pedida vira um item com seu peso e uma questão em rascunho
 * já ligada a ele — assim o professor abre a solicitação e encontra os
 * campos prontos, e o peso definido pelo PAEET nunca depende do que ele
 * digitar.
 */
class CriarSolicitacaoAction
{
    /**
     * @param  array<int, float|string>  $pesos  um peso por questão pedida, na ordem
     */
    public function executar(
        User $autor,
        Turma $turma,
        Disciplina $disciplina,
        User $professor,
        array $pesos,
        int $quantidadeAlternativas,
        \DateTimeInterface $prazo,
        ?string $observacoes = null,
    ): SolicitacaoProva {
        Gate::forUser($autor)->authorize('create', SolicitacaoProva::class);
        Gate::forUser($autor)->authorize('view', $turma);

        $this->validar($turma, $disciplina, $professor, $pesos, $quantidadeAlternativas);

        return DB::transaction(function () use (
            $autor, $turma, $disciplina, $professor, $pesos,
            $quantidadeAlternativas, $prazo, $observacoes
        ) {
            $solicitacao = SolicitacaoProva::create([
                'curso_id' => $turma->curso_id,
                'turma_id' => $turma->getKey(),
                'disciplina_id' => $disciplina->getKey(),
                'professor_id' => $professor->getKey(),
                'criado_por' => $autor->getKey(),
                'quantidade_questoes' => count($pesos),
                'quantidade_alternativas' => $quantidadeAlternativas,
                'prazo' => $prazo,
                'status' => StatusSolicitacao::Aberta,
                'observacoes' => $observacoes,
            ]);

            foreach (array_values($pesos) as $indice => $peso) {
                $item = $solicitacao->itens()->create([
                    'ordem' => $indice + 1,
                    'peso' => (float) $peso,
                ]);

                // A questão nasce junto, em rascunho. O peso fica fora do
                // `fillable` de propósito — é o que impede o professor de
                // alterá-lo — então aqui ele é copiado do item à mão.
                $questao = $solicitacao->questoes()->make([
                    'solicitacao_item_id' => $item->getKey(),
                    'disciplina_id' => $disciplina->getKey(),
                    'professor_id' => $professor->getKey(),
                ]);

                $questao->peso = $item->peso;
                $questao->save();
            }

            // Designar alguém para uma disciplina cria o vínculo docente se
            // ele ainda não existir — é o que dá ao professor acesso aos
            // resultados dela mais adiante.
            $professor->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplina->getKey(), 'turma_id' => $turma->getKey()],
                ['ativo' => true],
            );
            $professor->esquecerEscopo();

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties([
                    'turma' => $turma->nome,
                    'disciplina' => $disciplina->nome,
                    'professor' => $professor->nome,
                    'questoes' => count($pesos),
                    'soma_dos_pesos' => array_sum(array_map('floatval', $pesos)),
                    'prazo' => $prazo->format('Y-m-d H:i'),
                ])
                ->log("Solicitação de {$solicitacao->quantidade_questoes} questão(ões) aberta");

            return $solicitacao->refresh();
        });
    }

    /** @param array<int, float|string> $pesos */
    protected function validar(
        Turma $turma,
        Disciplina $disciplina,
        User $professor,
        array $pesos,
        int $quantidadeAlternativas,
    ): void {
        if ($pesos === []) {
            throw RegraDeNegocioException::porque('Informe ao menos uma questão com peso.');
        }

        foreach ($pesos as $peso) {
            if ((float) $peso <= 0) {
                throw RegraDeNegocioException::porque('Todo peso precisa ser maior que zero.');
            }
        }

        if ($quantidadeAlternativas < 2 || $quantidadeAlternativas > 6) {
            throw RegraDeNegocioException::porque('A prova deve ter de 2 a 6 alternativas por questão.');
        }

        if ((int) $disciplina->curso_id !== (int) $turma->curso_id) {
            throw RegraDeNegocioException::porque(
                "A disciplina {$disciplina->nome} não pertence ao curso desta turma."
            );
        }

        // A turma cursa as disciplinas do seu período, segundo a foto de
        // grade que congelou.
        $noPeriodo = $turma->disciplinasDoPeriodo()
            ->contains(fn ($item) => (int) $item->disciplina_id === (int) $disciplina->getKey());

        if (! $noPeriodo) {
            throw RegraDeNegocioException::porque(
                "A turma {$turma->nome} não cursa {$disciplina->nome} no {$turma->periodo}º período."
            );
        }

        if (! $professor->ativo) {
            throw RegraDeNegocioException::porque('A conta do professor está inativa.');
        }
    }
}
