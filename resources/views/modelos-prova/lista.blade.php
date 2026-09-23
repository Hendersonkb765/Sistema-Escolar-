<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou instituição"/>
                </x-campo>

                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model.live="mostrarInativos"
                           class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                    Mostrar também os desativados
                </label>
            </div>

            @can('create', App\Models\ModeloProva::class)
                <x-botao href="{{ route('modelos-prova.criar') }}" wire:navigate class="shrink-0">Novo modelo</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($modelos->isEmpty())
            <x-vazio titulo="Nenhum modelo de prova"
                     descricao="O modelo guarda o cabeçalho, o quadro de identificação do aluno e o rodapé da folha. É preciso ao menos um para montar provas.">
                <x-slot:acoes>
                    @can('create', App\Models\ModeloProva::class)
                        <x-botao href="{{ route('modelos-prova.criar') }}" wire:navigate>Novo modelo</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">
                                    Modelo {{ $this->setaDaColuna('nome') }}
                                </button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Eixo</th>
                            <th class="hidden px-4 py-2 md:table-cell">Provas</th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($modelos as $modelo)
                            <tr wire:key="modelo-{{ $modelo->id }}">
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $modelo->nome }}</span>
                                    <span class="block text-xs text-slate-400">
                                        {{ $modelo->instituicao ?: config('app.name') }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                    {{ $modelo->eixo->nome }}
                                </td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ $modelo->provas_count }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$modelo->ativo ? 'verde' : 'cinza'"
                                             :rotulo="$modelo->ativo ? 'Ativo' : 'Desativado'"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $modelo)
                                            <x-botao variante="discreto" wire:click="alternarAtivo({{ $modelo->id }})"
                                                     title="{{ $modelo->ativo
                                                        ? 'Deixa de aparecer na montagem; as provas já montadas continuam intactas.'
                                                        : 'Volta a aparecer na montagem de provas.' }}">
                                                {{ $modelo->ativo ? 'Desativar' : 'Reativar' }}
                                            </x-botao>
                                            <x-botao variante="secundario"
                                                     href="{{ route('modelos-prova.editar', $modelo) }}" wire:navigate>
                                                Editar
                                            </x-botao>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $modelos->links() }}</div>
        @endif
    </x-cartao>
</div>
