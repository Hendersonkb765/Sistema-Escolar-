<div class="space-y-4">
    <x-cartao>
        <x-campo rotulo="Buscar" para="busca" class="sm:max-w-sm">
            <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou código"/>
        </x-campo>
    </x-cartao>

    <x-cartao descricao="O cadastro completo de cursos, grades e turmas chega no milestone 2.">
        @if ($cursos->isEmpty())
            <x-vazio titulo="Nenhum curso no seu escopo"/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Curso {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Eixo</th>
                            <th class="px-4 py-2">Código</th>
                            <th class="hidden px-4 py-2 md:table-cell">Duração</th>
                            <th class="hidden px-4 py-2 md:table-cell">Turmas</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($cursos as $curso)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $curso->nome }}</td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">{{ $curso->eixo->nome }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $curso->codigo }}</td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">{{ $curso->duracao_anos }} anos</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">{{ $curso->turmas_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$curso->status->cor()" :rotulo="$curso->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <x-botao variante="discreto" href="{{ route('cursos.show', $curso) }}" wire:navigate>Ver</x-botao>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $cursos->links() }}</div>
        @endif
    </x-cartao>
</div>
