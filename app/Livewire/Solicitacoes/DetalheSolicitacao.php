<?php

namespace App\Livewire\Solicitacoes;

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Enums\StatusQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Acompanhamento e análise de uma solicitação.
 *
 * É aqui que a coordenação aprova cada questão ou devolve para correção,
 * com o motivo. O professor destinatário também chega a esta tela e vê o
 * caminho para responder.
 */
class DetalheSolicitacao extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public SolicitacaoProva $solicitacao;

    public string $acaoConfirmando = '';

    public string $motivo = '';

    /** Questão cuja devolução está sendo escrita. */
    public ?int $devolvendoQuestao = null;

    public string $comentarioDaDevolucao = '';

    /** Comentário opcional na aprovação, por questão. @var array<int, string> */
    public array $comentarioDaAprovacao = [];

    public function mount(SolicitacaoProva $solicitacao): void
    {
        $this->authorize('view', $solicitacao);

        $this->solicitacao = $solicitacao;
    }

    public function confirmar(string $acao): void
    {
        $this->acaoConfirmando = in_array($acao, ['encerrar', 'cancelar', 'reabrir'], true) ? $acao : '';
    }

    public function cancelarConfirmacao(): void
    {
        $this->acaoConfirmando = '';
        $this->motivo = '';
    }

    public function executarAcao(EncerrarSolicitacaoAction $action): void
    {
        $acao = $this->acaoConfirmando;

        abort_unless(in_array($acao, ['encerrar', 'cancelar', 'reabrir'], true), 400);

        $this->authorize($acao === 'cancelar' ? 'cancelar' : 'encerrar', $this->solicitacao);

        try {
            $this->solicitacao = $action->{$acao}(
                $this->solicitacao,
                auth()->user(),
                $this->motivo ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());
            $this->cancelarConfirmacao();

            return;
        }

        $mensagens = [
            'encerrar' => 'Solicitação encerrada. O professor não pode mais enviar questões.',
            'cancelar' => 'Solicitação cancelada.',
            'reabrir' => 'Solicitação reaberta para envio.',
        ];

        $this->notificarSucesso($mensagens[$acao]);

        $this->cancelarConfirmacao();
    }

    // ------------------------------------------------------------------
    // Análise das questões
    // ------------------------------------------------------------------

    public function aprovarQuestao(int $questaoId, AnalisarQuestaoAction $action): void
    {
        $questao = $this->questaoDaSolicitacao($questaoId);

        $this->authorize('analisar', $questao);

        $disciplina = $questao->parte->disciplina->nome;
        $ordem = $questao->ordem;

        try {
            $action->aprovar(
                questao: $questao,
                analista: auth()->user(),
                comentario: $this->comentarioDaAprovacao[$questaoId] ?? null,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        unset($this->comentarioDaAprovacao[$questaoId]);

        $this->notificarSucesso(
            "Questão {$ordem} de {$disciplina} aprovada. Ela já pode entrar em uma prova.",
            'Aprovada',
        );

        $this->solicitacao->refresh();
    }

    public function abrirDevolucao(int $questaoId): void
    {
        $this->authorize('analisar', $this->questaoDaSolicitacao($questaoId));

        $this->devolvendoQuestao = $questaoId;
        $this->comentarioDaDevolucao = '';
    }

    public function fecharDevolucao(): void
    {
        $this->devolvendoQuestao = null;
        $this->comentarioDaDevolucao = '';
    }

    public function devolverQuestao(AnalisarQuestaoAction $action): void
    {
        $questao = $this->questaoDaSolicitacao((int) $this->devolvendoQuestao);

        $this->authorize('analisar', $questao);

        $disciplina = $questao->parte->disciplina->nome;
        $ordem = $questao->ordem;

        try {
            $action->rejeitar(
                questao: $questao,
                analista: auth()->user(),
                comentario: $this->comentarioDaDevolucao,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarAtencao(
            "Questão {$ordem} de {$disciplina} devolvida. O professor vê o seu comentário e pode corrigi-la.",
            'Devolvida para correção',
        );

        $this->fecharDevolucao();
        $this->solicitacao->refresh();
    }

    /** Aprova de uma vez as questões ainda sem decisão. */
    public function aprovarPendentes(AnalisarQuestaoAction $action): void
    {
        $aprovadas = 0;

        foreach ($this->questoesAnalisaveis() as $questao) {
            if (auth()->user()->cannot('analisar', $questao)) {
                continue;
            }

            try {
                $action->aprovar($questao, auth()->user());
                $aprovadas++;
            } catch (RegraDeNegocioException $excecao) {
                $this->notificarErro($excecao->getMessage());

                return;
            }
        }

        $this->solicitacao->refresh();

        $aprovadas === 0
            ? $this->notificarInfo('Nenhuma questão pendente de análise.')
            : $this->notificarSucesso(
                $aprovadas === 1
                    ? 'Uma questão aprovada.'
                    : "{$aprovadas} questões aprovadas.",
                'Análise concluída',
            );
    }

    protected function questaoDaSolicitacao(int $questaoId): Questao
    {
        return Questao::query()
            ->where('solicitacao_id', $this->solicitacao->getKey())
            ->with(['parte.disciplina', 'solicitacao'])
            ->findOrFail($questaoId);
    }

    /** @return Collection<int, Questao> */
    protected function questoesAnalisaveis(): Collection
    {
        return $this->solicitacao->questoes()
            ->whereIn('status', [StatusQuestao::Enviada->value, StatusQuestao::EmAnalise->value])
            ->with(['parte.disciplina', 'solicitacao'])
            ->get();
    }

    public function render(): View
    {
        $this->solicitacao->load(['turma.curso.eixo', 'criadoPor']);

        $partes = $this->solicitacao->partes()
            ->with(['disciplina', 'professor', 'solicitacao'])
            ->get();

        $questoes = $this->solicitacao->questoes()
            ->with(['parte.disciplina', 'alternativas', 'blocos', 'feedbacks.analisadoPor'])
            ->get()
            ->sortBy(fn (Questao $questao) => [$questao->parte->ordem, $questao->ordem])
            ->values();

        return view('solicitacoes.detalhe', [
            // Agrupadas por parte: a prova é lida disciplina a disciplina.
            'partes' => $partes,
            'questoesPorParte' => $questoes->groupBy('solicitacao_parte_id'),
            'completas' => $this->solicitacao->questoesCompletas(),
            'podeResponder' => auth()->user()->can('responder', $this->solicitacao)
                && $this->solicitacao->aceitaEnvio(),
            'resumo' => [
                'aprovadas' => $questoes->filter(fn (Questao $q) => $q->status === StatusQuestao::Aprovada)->count(),
                'devolvidas' => $questoes->filter(fn (Questao $q) => $q->status === StatusQuestao::Rejeitada)->count(),
                'aguardando' => $questoes->filter(fn (Questao $q) => $q->status->analisavel())->count(),
                'rascunho' => $questoes->filter(fn (Questao $q) => $q->status === StatusQuestao::Rascunho)->count(),
            ],
        ])->layout('components.layouts.app', [
            'titulo' => $this->solicitacao->identificacao(),
            'subtitulo' => 'Turma '.$this->solicitacao->turma->nome
                .' · '.$partes->count().' disciplina(s)'
                .' · '.$this->solicitacao->totalDeQuestoes().' questão(ões)'
                .' · prazo '.$this->solicitacao->prazo->format('d/m/Y H:i'),
        ]);
    }
}
