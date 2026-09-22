<?php

namespace App\Livewire\Concerns;

/**
 * Avisos flutuantes.
 *
 * `notificar…` aparece na hora, sem recarregar a página — é o caso de
 * salvar um rascunho. `flash…` sobrevive a um redirecionamento e é o que
 * se usa quando a ação termina em outra tela.
 */
trait Notifica
{
    public function notificarSucesso(string $mensagem, ?string $titulo = null): void
    {
        $this->dispatch('notificar', tipo: 'sucesso', mensagem: $mensagem, titulo: $titulo);
    }

    public function notificarErro(string $mensagem, ?string $titulo = null): void
    {
        $this->dispatch('notificar', tipo: 'erro', mensagem: $mensagem, titulo: $titulo);
    }

    public function notificarAtencao(string $mensagem, ?string $titulo = null): void
    {
        $this->dispatch('notificar', tipo: 'atencao', mensagem: $mensagem, titulo: $titulo);
    }

    public function notificarInfo(string $mensagem, ?string $titulo = null): void
    {
        $this->dispatch('notificar', tipo: 'info', mensagem: $mensagem, titulo: $titulo);
    }

    /** Aviso que precisa sobreviver a um redirecionamento. */
    public function flashSucesso(string $mensagem): void
    {
        session()->flash('sucesso', $mensagem);
    }

    public function flashErro(string $mensagem): void
    {
        session()->flash('erro', $mensagem);
    }
}
