<div class="space-y-4">
    @if ($ehGestao && $aguardando > 0)
        <x-alerta tipo="info" titulo="{{ $aguardando }} questão(ões) aguardando sua análise">
            Abra a solicitação para aprovar ou devolver cada uma, com o motivo.
        </x-alerta>
    @endif

    <x-cartao>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-campo rotulo="Buscar" para="busca">
                <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Enunciado, disciplina ou professor"/>
            </x-campo>

            <x-campo rotulo="Situação" para="filtro-status">
                <x-select id="filtro-status" wire:model.live="filtroStatus">
                    <option value="">Todas</option>
                    @foreach ($situacoes as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </x-select>
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
        </div>
    </x-cartao>

    <x-cartao>
        @if ($questoes->isEmpty())
            <x-vazio titulo="Nenhuma questão nesta situação"
                     :descricao="$ehGestao
                        ? 'Quando os professores enviarem questões, elas aparecem aqui para análise.'
                        : 'As questões que você escrever aparecem aqui com o resultado da análise.'"/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Questão</th>
                            <th class="hidden px-4 py-2 sm:table-cell">Disciplina</th>
                            <th class="hidden px-4 py-2 lg:table-cell">Turma</th>
                            @if ($ehGestao)
                                <th class="hidden px-4 py-2 lg:table-cell">Professor</th>
                            @endif
                            <th class="hidden px-4 py-2 md:table-cell">Peso</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('status')" class="font-semibold">
                                    Situação {{ $this->setaDaColuna('status') }}
                                </button>
                            </th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($questoes as $questao)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 sm:px-6">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        Questão {{ $questao->item->ordem }}
                                        @if ($questao->versao > 1)
                                            <x-badge cor="cinza">v{{ $questao->versao }}</x-badge>
                                        @endif
                                    </p>
                                    <p class="max-w-md truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ $questao->enunciado ?: 'Ainda não preenchida' }}
                                    </p>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">
                                    {{ $questao->disciplina->nome }}
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 lg:table-cell dark:text-slate-300">
                                    {{ $questao->solicitacao->turma->nome }}
                                </td>
                                @if ($ehGestao)
                                    <td class="hidden px-4 py-3 text-slate-600 lg:table-cell dark:text-slate-300">
                                        {{ $questao->professor->nome }}
                                    </td>
                                @endif
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ number_format((float) $questao->peso, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <x-badge :cor="$questao->status->cor()" :rotulo="$questao->status->rotulo()"/>
                                        @if ($questao->feedbacks_count > 0)
                                            <span class="text-xs text-slate-400">{{ $questao->feedbacks_count }} análise(s)</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @can('analisar', $questao)
                                        <x-botao variante="secundario"
                                                 href="{{ route('solicitacoes.show', $questao->solicitacao) }}" wire:navigate>
                                            Analisar
                                        </x-botao>
                                    @else
                                        @can('update', $questao)
                                            <x-botao variante="{{ $questao->status === App\Enums\StatusQuestao::Rejeitada ? 'primario' : 'discreto' }}"
                                                     href="{{ route('solicitacoes.responder', $questao->solicitacao) }}" wire:navigate>
                                                {{ $questao->status === App\Enums\StatusQuestao::Rejeitada ? 'Corrigir' : 'Abrir' }}
                                            </x-botao>
                                        @else
                                            <x-botao variante="discreto"
                                                     href="{{ route('solicitacoes.show', $questao->solicitacao) }}" wire:navigate>
                                                Ver
                                            </x-botao>
                                        @endcan
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $questoes->links() }}</div>
        @endif
    </x-cartao>
</div>
