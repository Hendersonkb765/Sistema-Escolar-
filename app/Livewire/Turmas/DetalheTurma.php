<?php

namespace App\Livewire\Turmas;

use App\Actions\Academico\AvancarTurmaAction;
use App\Actions\Academico\ConcluirTurmaAction;
use App\Actions\Academico\DesligarAlunoAction;
use App\Enums\StatusAluno;
use App\Enums\StatusGrade;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\ConfirmaExclusao;
use App\Livewire\Concerns\Notifica;
use App\Models\Aluno;
use App\Models\Exclusao;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class DetalheTurma extends Component
{
    use AuthorizesRequests;
    use ConfirmaExclusao;
    use Notifica;

    public Turma $turma;

    public bool $painelAvancoAberto = false;

    /** Vazio = a própria turma avança de ano; preenchido = remanejamento. */
    public string $turmaDestinoId = '';

    public string $novoNome = '';

    public string $novoPeriodoLetivo = '';

    public string $observacoesAvanco = '';

    public bool $painelConclusaoAberto = false;

    public string $observacoesConclusao = '';

    /** O aluno cujo desligamento está aberto na lista, por id. */
    public ?int $alunoDesligando = null;

    public string $situacaoDesligamento = '';

    public string $motivoDesligamento = '';

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

    public function abrirPainelConclusao(): void
    {
        $this->authorize('encerrar', $this->turma);

        $this->painelConclusaoAberto = true;
    }

    public function fecharPainelConclusao(): void
    {
        $this->painelConclusaoAberto = false;
        $this->reset('observacoesConclusao');
    }

    /**
     * Fim do percurso: a turma chegou ao último período e encerra.
     *
     * Fica na mesma tela — a turma continua existindo, só muda de
     * estado —, então notifica por evento. O avanço, que leva para outra
     * turma, é que usa flash.
     */
    public function concluirTurma(ConcluirTurmaAction $action): void
    {
        $this->authorize('encerrar', $this->turma);

        try {
            $resultado = $action->executar(
                turma: $this->turma,
                autor: auth()->user(),
                observacoes: $this->observacoesConclusao ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->fecharPainelConclusao();

        $this->turma = $resultado['turma'];

        $concluintes = $resultado['concluintes'];

        $this->notificarSucesso(
            "Turma concluída. {$concluintes} aluno(s) passaram a concluinte."
        );
    }

    /*
     * Retirar aluno da turma.
     *
     * Abre dentro da própria linha dele, e não num painel no alto da
     * página: numa turma de trinta, um formulário longe da linha é um
     * convite a desligar o aluno errado.
     */
    public function abrirDesligamento(int $alunoId): void
    {
        $aluno = $this->alunoDaTurma($alunoId);

        $this->authorize('desligar', $aluno);

        $this->alunoDesligando = $aluno->getKey();
        $this->situacaoDesligamento = StatusAluno::Transferido->value;
        $this->motivoDesligamento = '';
    }

    public function fecharDesligamento(): void
    {
        $this->reset(['alunoDesligando', 'situacaoDesligamento', 'motivoDesligamento']);
    }

    public function desligarAluno(DesligarAlunoAction $action): void
    {
        $aluno = $this->alunoDaTurma((int) $this->alunoDesligando);

        $this->authorize('desligar', $aluno);

        $situacao = StatusAluno::tryFrom($this->situacaoDesligamento);

        if ($situacao === null) {
            $this->notificarErro('Escolha para onde o aluno está saindo.');

            return;
        }

        try {
            $action->executar(
                aluno: $aluno,
                autor: auth()->user(),
                situacao: $situacao,
                motivo: $this->motivoDesligamento,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $nome = $aluno->nome;

        $this->fecharDesligamento();

        $this->notificarSucesso(
            "{$nome} foi retirado(a) da turma como {$situacao->rotulo()}. "
            .'As notas e o histórico dele(a) continuam aqui.'
        );
    }

    /**
     * O aluno pelo id, confinado a esta turma.
     *
     * Pelo relacionamento, e não por `Aluno::find`: o id chega do
     * navegador, e sem este recorte um id de outra turma — ou de outro
     * Eixo — seria desligado a partir desta tela.
     */
    protected function alunoDaTurma(int $alunoId): Aluno
    {
        return $this->turma->alunos()->whereKey($alunoId)->firstOrFail();
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

    /** A turma desta tela saiu: volta para a lista. */
    protected function depoisDeExcluir(Exclusao $lote): void
    {
        $this->flashSucesso($this->mensagemDeExcluido($lote));
        $this->redirectRoute('turmas.index', navigate: true);
    }

    public function render(): View
    {
        $this->turma->load(['curso.eixo', 'grade']);

        return view('turmas.detalhe', [
            'disciplinasDoPeriodo' => $this->turma->disciplinasDoPeriodo(),
            'alunos' => $this->turma->alunos()->orderBy('nome')->get(),
            'situacoesDeSaida' => StatusAluno::desligamentos(),
            // `grade` entra aqui porque o histórico mostra a versão que
            // estava congelada à época do registro.
            'historicos' => $this->turma->historicos()
                ->with(['registradoPor', 'grade'])
                ->latest('created_at')
                ->get(),
            'destinos' => $this->turmasDestinoPossiveis(),
            // Quantos deixam de ser "ativo" ao concluir: o painel diz o
            // que vai acontecer antes de acontecer.
            'alunosAtivos' => $this->turma->alunos()->where('status', StatusAluno::Ativo)->count(),
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
