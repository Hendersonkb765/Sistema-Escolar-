<?php

namespace App\Livewire\Turmas;

use App\Actions\Academico\AvancarTurmaAction;
use App\Enums\StatusGrade;
use App\Exceptions\RegraDeNegocioException;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class DetalheTurma extends Component
{
    use AuthorizesRequests;

    public Turma $turma;

    public bool $painelAvancoAberto = false;

    /** Vazio = a própria turma avança de ano; preenchido = remanejamento. */
    public string $turmaDestinoId = '';

    public string $novaIdentificacao = '';

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
        $this->novaIdentificacao = $this->turma->identificacaoParaAno($this->turma->ano_curso + 1);
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
            session()->flash('erro', 'Turma de destino inválida.');

            return;
        }

        try {
            $resultado = $action->executar(
                turma: $this->turma,
                autor: auth()->user(),
                turmaDestino: $destino,
                novaIdentificacao: $this->novaIdentificacao ?: null,
                novoPeriodoLetivo: $this->novoPeriodoLetivo ?: null,
                observacoes: $this->observacoesAvanco ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());

            return;
        }

        $this->fecharPainelAvanco();

        $turma = $resultado['turma'];
        $quantidade = $resultado['disciplinas']->count();

        $mensagem = $destino === null
            ? "Turma avançada para o {$turma->ano_curso}º ano ({$turma->identificacao}). {$quantidade} disciplina(s) neste ano."
            : "{$resultado['alunos_movidos']} aluno(s) movido(s) para {$turma->identificacao}. Turma de origem concluída.";

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
            ->where('ano_curso', $this->turma->ano_curso + 1)
            ->whereKeyNot($this->turma->getKey())
            ->orderBy('identificacao')
            ->get();
    }

    public function render(): View
    {
        $this->turma->load(['curso.eixo', 'grade']);

        return view('turmas.detalhe', [
            'disciplinasDoAno' => $this->turma->disciplinasDoAno(),
            'alunos' => $this->turma->alunos()->orderBy('nome')->get(),
            'historicos' => $this->turma->historicos()->with('registradoPor')->latest('created_at')->get(),
            'destinos' => $this->turmasDestinoPossiveis(),
            'gradeDesatualizada' => $this->gradeDesatualizada(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->turma->identificacao,
            'subtitulo' => $this->turma->curso->nome.' · '.$this->turma->ano_curso.'º ano · '.$this->turma->periodo_letivo,
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
