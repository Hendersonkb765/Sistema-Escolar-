<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-5">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Disciplina, turma ou professor"/>
                </x-campo>

                @if ($ehGestao)
                    <x-campo rotulo="Curso" para="filtro-curso">
                        <x-select id="filtro-curso" wire:model.live="filtroCurso">
                            <option value="">Todos</option>
                            @foreach ($cursos as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>
                @endif

                <x-campo rotulo="Bimestre" para="filtro-bimestre">
                    <x-select id="filtro-bimestre" wire:model.live="filtroBimestre">
                        <option value="">Todos</option>
                        @foreach ($bimestres as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
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

                <x-campo rotulo="Prazo" para="filtro-prazo">
                    <x-select id="filtro-prazo" wire:model.live="filtroPrazo">
                        <option value="">Todos</option>
                        <option value="pendentes">Aguardando envio</option>
                        <option value="atrasadas">Atrasadas</option>
                        <option value="devolvidas">Com questão devolvida</option>
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\SolicitacaoProva::class)
                <x-botao href="{{ route('solicitacoes.criar') }}" wire:navigate class="shrink-0">Nova solicitação</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($solicitacoes->isEmpty())
            <x-vazio titulo="Nenhuma solicitação encontrada"
                     :descricao="$ehGestao
                        ? 'Abra uma solicitação para pedir questões a um professor.'
                        : 'Quando a coordenação pedir questões a você, elas aparecem aqui.'">
                <x-slot:acoes>
                    @can('create', App\Models\SolicitacaoProva::class)
                        <x-botao href="{{ route('solicitacoes.criar') }}" wire:navigate>Nova solicitação</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Prova</th>
                            <th class="hidden px-4 py-2 sm:table-cell">Turma</th>
                            @if ($ehGestao)
                                <th class="hidden px-4 py-2 lg:table-cell">Professor</th>
                            @endif
                            <th class="hidden px-4 py-2 md:table-cell">
                                Questões
                            </th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('prazo')" class="font-semibold">
                                    Prazo {{ $this->setaDaColuna('prazo') }}
                                </button>
                            </th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($solicitacoes as $solicitacao)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 sm:px-6">
                                    <a href="{{ route('solicitacoes.show', $solicitacao) }}" wire:navigate
                                       class="font-medium text-marca-600 hover:underline dark:text-marca-400">
                                        {{ $solicitacao->identificacao() }}
                                    </a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $solicitacao->partes->pluck('disciplina.nome')->join(', ') }}
                                    </p>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                    {{ $solicitacao->turma->nome }}
                                    <span class="text-xs text-slate-400">· {{ $solicitacao->turma->curso->nome }}</span>
                                </td>
                                @if ($ehGestao)
                                    <td class="hidden px-4 py-3 text-slate-600 lg:table-cell dark:text-slate-300">
                                        {{ $solicitacao->partes->pluck('professor.nome')->unique()->join(', ') }}
                                    </td>
                                @endif
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ $solicitacao->questoes_count }}
                                    <span class="block text-xs text-slate-400">
                                        {{ $solicitacao->partes_count }} disciplina(s)
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ $solicitacao->prazo->format('d/m/Y') }}
                                    <span class="block text-xs text-slate-400">{{ $solicitacao->prazo->format('H:i') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <x-badge :cor="$solicitacao->status->cor()" :rotulo="$solicitacao->status->rotulo()"/>
                                        @if ($rotulo = $solicitacao->rotuloDePrazo())
                                            <x-badge cor="vermelho" :rotulo="$rotulo"/>
                                        @endif
                                        @if ($solicitacao->questoes_devolvidas_count > 0)
                                            <x-badge cor="vermelho">
                                                {{ $solicitacao->questoes_devolvidas_count }} devolvida(s)
                                            </x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @can('responder', $solicitacao)
                                        @if ($solicitacao->questoes_devolvidas_count > 0)
                                            <x-botao href="{{ route('solicitacoes.responder', $solicitacao) }}" wire:navigate>
                                                Corrigir {{ $solicitacao->questoes_devolvidas_count }}
                                            </x-botao>
                                        @elseif ($solicitacao->aceitaEnvio())
                                            <x-botao variante="secundario" href="{{ route('solicitacoes.responder', $solicitacao) }}" wire:navigate>
                                                Responder
                                            </x-botao>
                                        @endif
                                    @else
                                        <x-botao variante="discreto" href="{{ route('solicitacoes.show', $solicitacao) }}" wire:navigate>
                                            Ver
                                        </x-botao>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $solicitacoes->links() }}</div>
        @endif
    </x-cartao>
</div>
