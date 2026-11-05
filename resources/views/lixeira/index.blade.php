<div class="mx-auto max-w-4xl space-y-4">
    <x-cartao titulo="Excluídos" :icone="App\Support\Navegacao::icone('lixeira')"
              descricao="Restaurar devolve o registro e tudo o que saiu com ele, como estavam.">
        @if ($lotes->isEmpty())
            <x-vazio titulo="A Lixeira está vazia" descricao="O que for excluído nas telas de cadastro aparece aqui."/>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($lotes as $lote)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3" wire:key="lote-{{ $lote->id }}" data-lote>
                        <div class="min-w-0 space-y-0.5">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $lote->rotulo }}</p>
                            @if ($lote->oQueSaiuJunto() !== '')
                                <p class="text-sm text-slate-600 dark:text-slate-300">Saiu junto: {{ $lote->oQueSaiuJunto() }}.</p>
                            @endif
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Excluído por {{ $lote->excluidoPor?->nome ?? 'conta removida' }} em {{ $lote->created_at->format('d/m/Y \à\s H:i') }}
                            </p>
                        </div>
                        <x-botao variante="secundario" wire:click="restaurar({{ $lote->id }})"
                                 wire:confirm="Restaurar {{ $lote->rotulo }} e tudo o que saiu junto?">Restaurar</x-botao>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-cartao>
</div>
