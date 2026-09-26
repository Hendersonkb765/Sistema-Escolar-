<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Arquivo ou prova"/>
                </x-campo>

                <x-campo rotulo="Situação" para="filtro-status">
                    <x-select id="filtro-status" wire:model.live="filtroStatus">
                        <option value="">Todas</option>
                        @foreach ($situacoes as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\Importacao::class)
                <x-botao href="{{ route('importacoes.criar') }}" wire:navigate class="shrink-0">Importar resultados</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($importacoes->isEmpty())
            <x-vazio titulo="Nenhuma importação ainda"
                     descricao="Envie a planilha do leitor de folhas para trazer os acertos de cada aluno.">
                <x-slot:acoes>
                    @can('create', App\Models\Importacao::class)
                        <x-botao href="{{ route('importacoes.criar') }}" wire:navigate>Importar resultados</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Arquivo</th>
                            <th class="hidden px-4 py-2 sm:table-cell">Prova</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('total_linhas')" class="font-semibold">
                                    Linhas {{ $this->setaDaColuna('total_linhas') }}
                                </button>
                            </th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="hidden px-4 py-2 lg:table-cell">
                                <button type="button" wire:click="ordenar('created_at')" class="font-semibold">
                                    Enviada {{ $this->setaDaColuna('created_at') }}
                                </button>
                            </th>
                            <th class="px-4 py-2 text-right sm:px-6">Resultados</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($importacoes as $importacao)
                            <tr wire:key="importacao-{{ $importacao->id }}">
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ $importacao->nome_original ?: 'planilha' }}
                                    </span>
                                    <span class="block text-xs text-slate-400">por {{ $importacao->usuario->nome }}</span>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                    {{ $importacao->prova->titulo }}
                                    <span class="block text-xs text-slate-400">
                                        {{ $importacao->prova->turma->nome }} · {{ $importacao->prova->turma->curso->nome }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $importacao->total_linhas }}
                                    @if ($importacao->total_erros > 0)
                                        <span class="block text-xs text-rose-600 dark:text-rose-400">
                                            {{ $importacao->total_erros }} recusada(s)
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$importacao->status->cor()" :rotulo="$importacao->status->rotulo()"/>
                                </td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-500 lg:table-cell dark:text-slate-400">
                                    {{ $importacao->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @if ($importacao->confirmada_em)
                                        <x-botao variante="secundario"
                                                 href="{{ route('resultados.index', ['prova' => $importacao->prova_id]) }}"
                                                 wire:navigate>
                                            Ver notas
                                        </x-botao>
                                    @else
                                        <span class="text-xs text-slate-400">Nada gravado</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $importacoes->links() }}</div>
        @endif
    </x-cartao>
</div>
