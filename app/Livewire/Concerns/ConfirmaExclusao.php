<?php

namespace App\Livewire\Concerns;

use App\Actions\Exclusao\ExcluirRegistroAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
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
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Excluir com confirmação, em qualquer tela.
 *
 * Pedir a exclusão calcula o impacto e abre a primeira confirmação; cada
 * confirmar avança uma etapa, e só a última exclui. Turma e curso pedem
 * duas: a segunda reforça que outros registros saem junto.
 *
 * A etapa fica no servidor (`#[Locked]`): mexer na requisição não pula a
 * segunda confirmação.
 *
 * Quem usa precisa de AuthorizesRequests e Notifica. Para fazer outra
 * coisa depois de excluir — sair da tela do registro excluído, por
 * exemplo —, sobrescreve {@see depoisDeExcluir()}.
 */
trait ConfirmaExclusao
{
    /**
     * @var array{tipo: string, id: int, rotulo: string, contagens: array<string, int>, avisos: array<int, string>, impedimento: ?string, etapa: int, etapas: int}|null
     */
    #[Locked]
    public ?array $exclusao = null;

    /** @return array<string, class-string<Model&Excluivel>> */
    protected function tiposExcluiveis(): array
    {
        return [
            'eixo' => Eixo::class,
            'curso' => Curso::class,
            'turma' => Turma::class,
            'disciplina' => Disciplina::class,
            'grade' => GradeCurricular::class,
            'aluno' => Aluno::class,
            'usuario' => User::class,
            'solicitacao' => SolicitacaoProva::class,
            'prova' => Prova::class,
            'importacao' => Importacao::class,
            'modelo-prova' => ModeloProva::class,
            'modelo-documento' => ModeloDocumento::class,
        ];
    }

    public function pedirExclusao(string $tipo, int $id, ExcluirRegistroAction $action): void
    {
        $registro = $this->registroParaExcluir($tipo, $id);

        $this->authorize('delete', $registro);

        $plano = $action->planejar($registro);

        $this->exclusao = [
            'tipo' => $tipo,
            'id' => $id,
            'rotulo' => $plano->rotulo,
            'contagens' => $plano->contagens,
            'avisos' => $plano->avisos,
            'impedimento' => $plano->impedimento,
            'etapa' => 1,
            'etapas' => $plano->confirmacoes,
        ];
    }

    public function confirmarExclusao(ExcluirRegistroAction $action): void
    {
        // Impedida, a confirmação só explica: não há o que confirmar.
        if ($this->exclusao === null || $this->exclusao['impedimento'] !== null) {
            return;
        }

        if ($this->exclusao['etapa'] < $this->exclusao['etapas']) {
            $this->exclusao['etapa']++;

            return;
        }

        $registro = $this->registroParaExcluir($this->exclusao['tipo'], $this->exclusao['id']);

        try {
            $lote = $action->executar($registro, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->exclusao = null;
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->exclusao = null;
        $this->depoisDeExcluir($lote);
    }

    public function cancelarExclusao(): void
    {
        $this->exclusao = null;
    }

    protected function depoisDeExcluir(Exclusao $lote): void
    {
        $this->notificarSucesso($this->mensagemDeExcluido($lote));
    }

    protected function mensagemDeExcluido(Exclusao $lote): string
    {
        return auth()->user()->can('administrarLixeira')
            ? "{$lote->rotulo} foi para a Lixeira. Se foi engano, restaure por lá."
            : "{$lote->rotulo} foi para a Lixeira. Se foi engano, o PAEET Admin pode restaurar.";
    }

    protected function registroParaExcluir(string $tipo, int $id): Model&Excluivel
    {
        $classe = $this->tiposExcluiveis()[$tipo] ?? abort(404);

        return $classe::query()->findOrFail($id);
    }
}
