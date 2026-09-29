<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-wrap items-center gap-2 p-4 sm:p-6">
            <x-botao :variante="$caixa === 'recebidos' ? 'primario' : 'secundario'"
                     wire:click="trocarCaixa('recebidos')">
                Recebidos
                @if ($pendentes > 0)
                    <x-badge cor="amarelo" :rotulo="$pendentes"/>
                @endif
            </x-botao>
            <x-botao :variante="$caixa === 'enviados' ? 'primario' : 'secundario'"
                     wire:click="trocarCaixa('enviados')">
                Enviados
            </x-botao>
        </div>
    </x-cartao>

    <x-cartao>
        @if ($compartilhamentos->isEmpty())
            <x-vazio :titulo="$caixa === 'recebidos' ? 'Nenhum modelo recebido' : 'Nenhum modelo enviado'"
                     :descricao="$caixa === 'recebidos'
                        ? 'Quando outro PAEET compartilhar um modelo de documento com você, ele aparece aqui para você aceitar ou recusar.'
                        : 'Os modelos que você oferecer a outro PAEET aparecem aqui, com a resposta de cada um.'"/>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($compartilhamentos as $item)
                    <li wire:key="compartilhamento-{{ $item->id }}" class="px-4 py-4 sm:px-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $item->modelo->nome }}
                                </p>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    @if ($caixa === 'recebidos')
                                        de {{ $item->remetente->nome }}
                                    @else
                                        para {{ $item->destinatario->nome }}
                                    @endif
                                    · {{ $item->created_at->format('d/m/Y') }}
                                </p>
                                @if ($item->mensagem)
                                    <p class="mt-1 text-sm italic text-slate-600 dark:text-slate-300">
                                        “{{ $item->mensagem }}”
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-badge :cor="$item->status->cor()" :rotulo="$item->status->rotulo()"/>

                                <x-botao variante="discreto" wire:click="espiar({{ $item->id }})">
                                    {{ $espiando === $item->id ? 'Fechar' : 'Ver o texto' }}
                                </x-botao>

                                @can('responder', $item)
                                    @if ($item->pendente())
                                        <x-botao wire:click="aceitar({{ $item->id }})"
                                                 title="Cria uma cópia sua deste modelo, que você pode editar.">
                                            Aceitar
                                        </x-botao>
                                        <x-botao variante="secundario" wire:click="recusar({{ $item->id }})"
                                                 title="Nada é criado. O modelo não entra na sua lista.">
                                            Recusar
                                        </x-botao>
                                    @endif
                                @endcan
                            </div>
                        </div>

                        @if ($item->status->respondido())
                            <p class="mt-2 text-xs text-slate-400">
                                Respondido em {{ $item->respondido_em?->format('d/m/Y \à\s H:i') }}
                                @if ($item->copia)
                                    · virou o modelo “{{ $item->copia->nome }}”
                                @endif
                            </p>
                        @endif

                        @if ($espiando === $item->id)
                            <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                                    Autor do modelo: {{ $item->modelo->autor?->nome ?? '—' }} ·
                                    {{ $item->modelo->tipo->rotulo() }}
                                </p>
                                <div class="previa-do-documento text-sm leading-relaxed text-slate-800 dark:text-slate-200">
                                    {!! $this->previa($item) !!}
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 px-4 pb-4 sm:px-6">{{ $compartilhamentos->links() }}</div>
        @endif
    </x-cartao>
</div>
