<?php

namespace App\Livewire\Notificacoes;

use App\Support\SinalDeAvisos;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * O sino no topo da tela: os avisos da coordenação.
 *
 * Não há servidor de WebSocket — a hospedagem não roda processo
 * permanente. O navegador pergunta a cada 30 segundos por um contador
 * (SinalDeAvisos) num endereço que não abre o banco; só quando o
 * contador muda ele chama `verificar()`, que busca os avisos novos e os
 * mostra em balão. Uma conexão por aviso, e não uma a cada 30 segundos.
 *
 * A janela de cada pergunta é [desde, agora), em segundos inteiros: um
 * aviso gravado no mesmo segundo da pergunta fica para a seguinte, e
 * nenhum aparece duas vezes.
 */
class Sino extends Component
{
    /** Até quantos avisos novos viram balão de uma vez; o resto, um resumo. */
    public const BALOES = 3;

    #[Locked]
    public string $desde = '';

    /**
     * A primeira janela começa no segundo seguinte ao da abertura: o que
     * já existia quando a tela abriu está na lista do sino, e virar balão
     * na primeira consulta seria avisar duas vezes.
     */
    public function mount(): void
    {
        $this->desde = now()->startOfSecond()->addSecond()->toDateTimeString();
    }

    public function verificar(): void
    {
        $agora = now()->startOfSecond();

        $novos = auth()->user()->unreadNotifications()
            ->where('created_at', '>=', $this->desde)
            ->where('created_at', '<', $agora)
            ->reorder('created_at')
            ->get();

        $this->desde = $agora->toDateTimeString();

        foreach ($novos->take(self::BALOES) as $aviso) {
            $this->dispatch('notificar', tipo: 'info', titulo: $aviso->data['titulo'], mensagem: $aviso->data['mensagem']);
        }

        if ($novos->count() > self::BALOES) {
            $resto = $novos->count() - self::BALOES;
            $this->dispatch('notificar', tipo: 'info', titulo: null, mensagem: "E mais {$resto} aviso(s) no sino.");
        }
    }

    /** Marca como lido e leva à tela do assunto. */
    public function abrir(string $id): void
    {
        /** @var DatabaseNotification $aviso */
        $aviso = auth()->user()->notifications()->whereKey($id)->firstOrFail();

        $aviso->markAsRead();

        $this->redirect($aviso->data['url'], navigate: true);
    }

    /**
     * Um endereço novo para o sinal, quando o antigo deixou de valer —
     * chave da aplicação trocada, endereço que chega diferente pelo proxy.
     * O sino pede uma vez, ao receber 403.
     */
    public function enderecoDoSinal(): string
    {
        return SinalDeAvisos::url(auth()->user());
    }

    public function marcarTodasComoLidas(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('notificacoes.sino', [
            'sinal' => SinalDeAvisos::url($usuario),
            'versao' => SinalDeAvisos::versao($usuario->id),
            'naoLidas' => $usuario->unreadNotifications()->count(),
            'recentes' => $usuario->notifications()->limit(8)->get(),
        ]);
    }
}
