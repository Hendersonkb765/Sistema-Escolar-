<?php

namespace App\Livewire\Turmas;

use App\Actions\Academico\AvancarTurmaAction;
use App\Enums\StatusGrade;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class DetalheTurma extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public Turma $turma;

    public bool $painelAvancoAberto = false;

    /** Vazio = a própria turma avança de ano; preenchido = remanejamento. */
    public string $turmaDestinoId = '';

    public string $novoNome = '';

    public string $novoPeriodoLetivo = '';

    public string $observacoesAvanco = '';

    public function mount(Turma $turma): void
    {
        $this->authorize('view', $turma);

        $this->turma = $turma;
    }

    public function abrirPainelAvanco(): void
    {
        $this->authorize('avancarAno', $this->turma);

        $this->painelAvancoAberto = true;
        $this->novoNome = $this->turma->nomeParaPeriodo($this->turma->periodo + 1);
        $this->novoPeriodoLetivo = $this->turma->periodo_letivo;
    }

    public function fecharPainelAvanco(): void
    {
        $this->painelAvancoAberto = false;
        $this->reset(['turmaDestinoId', 'observacoesAvanco']);
    }

    public function avancarAno(AvancarTurmaAction $action): void
    {
        $this->authorize('avancarAno', $this->turma);

        $destino = $this->turmaDestinoId === ''
            ? null
            : $this->turmasDestinoPossiveis()->firstWhere('id', (int) $this->turmaDestinoId);

        if ($this->turmaDestinoId !== '' && $destino === null) {
            $this->notificarErro('Turma de destino inválida.');

            return;
        }

        try {
            $resultado = $action->executar(
                turma: $this->turma,
                autor: auth()->user(),
                turmaDestino: $destino,
                novoNome: $this->novoNome ?: null,
                novoPeriodoLetivo: $this->novoPeriodoLetivo ?: null,
                observacoes: $this->observacoesAvanco ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->fecharPainelAvanco();

        $turma = $resultado['turma'];
        $quantidade = $resultado['disciplinas']->count();

        $mensagem = $destino === null
            ? "Turma avançada para o {$turma->periodo}º período ({$turma->nome}). {$quantidade} disciplina(s) neste período."
            : "{$resultado['alunos_movidos']} aluno(s) movido(s) para {$turma->nome}. Turma de origem concluída.";

        session()->flash('sucesso', $mensagem);

        $this->redirectRoute('turmas.show', $turma, navigate: true);
    }

    /**
     * Turmas do mesmo curso já abertas no ano seguinte — alvos válidos
     * para remanejamento.
     *
     * @return Collection<int, Turma>
     */
    public function turmasDestinoPossiveis(): Collection
    {
        if (! $this->turma->podeAvancar()) {
            return collect();
        }

        return Turma::query()
            ->visivelPara(auth()->user())
            ->where('curso_id', $this->turma->curso_id)
            ->where('periodo', $this->turma->periodo + 1)
            ->whereKeyNot($this->turma->getKey())
            ->orderBy('nome')
            ->get();
    }

    public function render(): View
    {
        $this->turma->load(['curso.eixo', 'grade']);

        return view('turmas.detalhe', [
            'disciplinasDoPeriodo' => $this->turma->disciplinasDoPeriodo(),
            'alunos' => $this->turma->alunos()->orderBy('nome')->get(),
            // `grade` entra aqui porque o histórico mostra a versão que
            // estava congelada à época do registro.
            'historicos' => $this->turma->historicos()
                ->with(['registradoPor', 'grade'])
                ->latest('created_at')
                ->get(),
            'destinos' => $this->turmasDestinoPossiveis(),
            'gradeDesatualizada' => $this->gradeDesatualizada(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->turma->nome,
            'subtitulo' => $this->turma->curso->nome.' · '.$this->turma->periodo.'º período · '.$this->turma->periodo_letivo,
        ]);
    }

    /**
     * Sinaliza quando existe uma versão de grade mais nova que a congelada
     * nesta turma. É só informativo: a troca nunca acontece sozinha.
     */
    protected function gradeDesatualizada(): ?GradeCurricular
    {
        return GradeCurricular::query()
            ->where('curso_id', $this->turma->curso_id)
            ->where('status', StatusGrade::Vigente)
            ->where('versao', '>', $this->turma->grade->versao)
            ->orderByDesc('versao')
            ->first();
    }
}
