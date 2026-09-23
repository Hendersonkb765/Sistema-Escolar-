<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Título ou turma"/>
                </x-campo>

                <x-campo rotulo="Turma" para="filtro-turma">
                    <x-select id="filtro-turma" wire:model.live="filtroTurma">
                        <option value="">Todas</option>
                        @foreach ($turmas as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </x-select>
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

            @can('create', App\Models\Prova::class)
                <x-botao href="{{ route('provas.criar') }}" wire:navigate class="shrink-0">Montar prova</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($provas->isEmpty())
            <x-vazio titulo="Nenhuma prova montada"
                     descricao="Depois de aprovar as questões de uma solicitação, monte a prova para gerar o PDF ou o Word.">
                <x-slot:acoes>
                    @can('create', App\Models\Prova::class)
                        <x-botao href="{{ route('provas.criar') }}" wire:navigate>Montar prova</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('titulo')" class="font-semibold">
                                    Prova {{ $this->setaDaColuna('titulo') }}
                                </button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Turma</th>
                            <th class="hidden px-4 py-2 md:table-cell">Questões</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('data_aplicacao')" class="font-semibold">
                                    Aplicação {{ $this->setaDaColuna('data_aplicacao') }}
                                </button>
                            </th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($provas as $prova)
                            <tr wire:key="prova-{{ $prova->id }}">
                                <td class="px-4 py-3 sm:px-6">
                                    <a href="{{ route('provas.show', $prova) }}" wire:navigate
                                       class="font-medium text-slate-900 hover:text-marca-700 dark:text-slate-100 dark:hover:text-marca-300">
                                        {{ $prova->titulo }}
                                    </a>
                                    <span class="block text-xs text-slate-400">
                                        {{ $prova->modelo->nome }}
                                        @if ($prova->versao > 1) · v{{ $prova->versao }} @endif
                                        · por {{ $prova->geradaPor?->nome ?? '—' }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                    {{ $prova->turma->nome }}
                                    <span class="block text-xs text-slate-400">{{ $prova->turma->curso->nome }}</span>
                                </td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ $prova->questoes_count }}
                                </td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $prova->data_aplicacao?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$prova->status->cor()" :rotulo="$prova->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-botao variante="discreto" href="{{ route('provas.pdf', $prova) }}">PDF</x-botao>
                                        <x-botao variante="discreto" href="{{ route('provas.docx', $prova) }}">Word</x-botao>
                                        <x-botao variante="secundario" href="{{ route('provas.show', $prova) }}" wire:navigate>
                                            Ver
                                        </x-botao>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $provas->links() }}</div>
        @endif
    </x-cartao>
</div>
