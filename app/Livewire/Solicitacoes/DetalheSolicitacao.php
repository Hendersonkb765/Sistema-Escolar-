<?php

namespace App\Livewire\Solicitacoes;

use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\SolicitacaoProva;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Acompanhamento de uma solicitação. O professor destinatário também
 * chega aqui e vê o caminho para responder.
 */
class DetalheSolicitacao extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public SolicitacaoProva $solicitacao;

    public string $acaoConfirmando = '';

    public string $motivo = '';

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

    public function render(): View
    {
        $this->solicitacao->load([
            'turma.curso.eixo',
            'disciplina',
            'professor',
            'criadoPor',
        ]);

        return view('solicitacoes.detalhe', [
            'questoes' => $this->solicitacao->questoes()
                ->with(['item', 'alternativas', 'blocos'])
                ->get()
                ->sortBy(fn ($questao) => $questao->item->ordem)
                ->values(),
            'completas' => $this->solicitacao->questoesCompletas(),
            'podeResponder' => auth()->user()->can('responder', $this->solicitacao)
                && $this->solicitacao->aceitaEnvio(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->solicitacao->disciplina->nome,
            'subtitulo' => 'Turma '.$this->solicitacao->turma->nome
                .' · '.$this->solicitacao->quantidade_questoes.' questão(ões)'
                .' · prazo '.$this->solicitacao->prazo->format('d/m/Y H:i'),
        ]);
    }
}
