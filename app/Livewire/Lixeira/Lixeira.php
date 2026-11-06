<?php

namespace App\Livewire\Lixeira;

use App\Actions\Exclusao\RestaurarExclusaoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Exclusao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * O que foi excluído e ainda pode voltar.
 *
 * Cada linha é um lote: o registro e tudo o que saiu com ele. Restaurar
 * devolve o lote inteiro. É do PAEET Admin, e de cada um só os lotes dos
 * Eixos dele — o Eixo é gravado na exclusão, porque depois o caminho até
 * ele pode estar na Lixeira também.
 */
class Lixeira extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public function mount(): void
    {
        $this->authorize('administrarLixeira');
    }

    public function restaurar(int $id, RestaurarExclusaoAction $action): void
    {
        $this->authorize('administrarLixeira');

        $lote = $this->lotes()->findOrFail($id);

        try {
            $action->executar($lote, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso("{$lote->rotulo} foi restaurado, com tudo o que tinha saído junto.");
    }

    /** @return Builder<Exclusao> */
    protected function lotes(): Builder
    {
        return Exclusao::query()
            ->naLixeira()
            ->where(fn (Builder $q) => $q->whereIn('eixo_id', auth()->user()->eixoIds())->orWhereNull('eixo_id'));
    }

    public function render(): View
    {
        return view('lixeira.index', [
            'lotes' => $this->lotes()->with('excluidoPor:id,nome')->latest()->get(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Lixeira',
            'subtitulo' => 'O que foi excluído e ainda pode voltar, com tudo o que saiu junto',
        ]);
    }
}
