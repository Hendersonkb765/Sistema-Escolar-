@props(['exclusao' => null])

{{--
    A confirmação de uma exclusão (App\Livewire\Concerns\ConfirmaExclusao).
    Primeira etapa: o que sai junto e o que muda. Segunda, quando há —
    turma e curso —: o reforço de que outros registros saem junto.
--}}
@if ($exclusao)
    @php
        $segunda = $exclusao['etapa'] === 2;
        $ultima = $exclusao['etapa'] === $exclusao['etapas'];
        $temImpacto = $exclusao['contagens'] !== [] || $exclusao['avisos'] !== [];
    @endphp

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" data-confirmacao-de-exclusao
         data-etapa="{{ $exclusao['etapa'] }}" role="dialog" aria-modal="true" aria-labelledby="titulo-da-exclusao"
         x-data x-on:keydown.escape.window="$wire.cancelarExclusao()">
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            @if ($exclusao['impedimento'] ?? null)
                <h2 id="titulo-da-exclusao" class="text-base font-semibold text-slate-900 dark:text-slate-100">Não dá para excluir {{ $exclusao['rotulo'] }} agora</h2>

                <p class="mt-3 text-sm text-slate-700 dark:text-slate-200" data-impedimento>{{ $exclusao['impedimento'] }}</p>

                <div class="mt-4 flex justify-end">
                    <x-botao variante="secundario" wire:click="cancelarExclusao">Fechar</x-botao>
                </div>
            @elseif (! $segunda)
                <h2 id="titulo-da-exclusao" class="text-base font-semibold text-slate-900 dark:text-slate-100">Excluir {{ $exclusao['rotulo'] }}?</h2>

                @if ($exclusao['contagens'] !== [])
                    <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">Sairão das telas junto:</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-slate-700 dark:text-slate-200">
                        @foreach ($exclusao['contagens'] as $rotulo => $quantos)
                            <li>{{ $quantos }} {{ $rotulo }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($exclusao['avisos'] !== [])
                    <ul class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                        @foreach ($exclusao['avisos'] as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif

                @unless ($temImpacto)
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">Nenhum outro registro depende dele.</p>
                @endunless
            @else
                <h2 id="titulo-da-exclusao" class="text-base font-semibold text-rose-700 dark:text-rose-300">Confirme de novo: excluir {{ $exclusao['rotulo'] }}</h2>

                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    Esta exclusão afeta outros registros e pode comprometer dados relacionados.
                    @if ($exclusao['contagens'] !== [])
                        Saem junto, para todos os usuários:
                        {{ collect($exclusao['contagens'])->map(fn ($n, $r) => "{$n} {$r}")->join(', ', ' e ') }}.
                    @endif
                    Provas, notas e solicitações ligadas a ele deixam de aparecer em todas as telas.
                </div>
            @endif

            @unless ($exclusao['impedimento'] ?? null)
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    Nada é apagado de vez: o PAEET Admin pode restaurar tudo pela Lixeira.
                </p>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <x-botao variante="secundario" wire:click="cancelarExclusao">Cancelar</x-botao>
                    <x-botao variante="perigo" wire:click="confirmarExclusao" wire:loading.attr="disabled">
                        {{ $ultima ? ($segunda ? 'Sim, excluir mesmo assim' : 'Excluir') : 'Continuar' }}
                    </x-botao>
                </div>
            @endunless
        </div>
    </div>
@endif
