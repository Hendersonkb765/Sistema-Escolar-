<?php

namespace App\Livewire\Notificacoes;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * O sino no topo da tela: os avisos da coordenação.
 *
 * Não há servidor de WebSocket — a hospedagem não roda processo
 * permanente. O sino pergunta ao servidor a cada 30 segundos, e o que
 * chegou desde a última pergunta aparece também como aviso flutuante.
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

    public function marcarTodasComoLidas(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('notificacoes.sino', [
            'naoLidas' => $usuario->unreadNotifications()->count(),
            'recentes' => $usuario->notifications()->limit(8)->get(),
        ]);
    }
}
