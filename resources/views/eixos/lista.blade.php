<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <x-campo rotulo="Buscar" para="busca" class="flex-1 sm:max-w-sm">
                <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou código"/>
            </x-campo>

            @can('create', App\Models\Eixo::class)
                <x-botao href="{{ route('eixos.criar') }}" wire:navigate class="shrink-0">Novo eixo</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($eixos->isEmpty())
            <x-vazio titulo="Nenhum eixo no seu escopo"
                     descricao="Um PAEET Admin precisa criar o eixo e vincular sua conta a ele."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Nome {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('codigo')" class="font-semibold">Código {{ $this->setaDaColuna('codigo') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Cursos</th>
                            <th class="hidden px-4 py-2 sm:table-cell">Disciplinas</th>
                            <th class="hidden px-4 py-2 md:table-cell">Usuários</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($eixos as $eixo)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $eixo->nome }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $eixo->codigo }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 sm:table-cell dark:text-slate-300">{{ $eixo->cursos_count }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 sm:table-cell dark:text-slate-300">{{ $eixo->disciplinas_count }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">{{ $eixo->usuarios_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$eixo->status->cor()" :rotulo="$eixo->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @can('update', $eixo)
                                        <x-botao variante="discreto" href="{{ route('eixos.editar', $eixo) }}" wire:navigate>Editar</x-botao>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $eixos->links() }}</div>
        @endif
    </x-cartao>
</div>
