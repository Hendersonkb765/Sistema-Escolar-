<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou RA"/>
                </x-campo>

                <x-campo rotulo="Turma" para="filtro-turma">
                    <x-select id="filtro-turma" wire:model.live="filtroTurma">
                        <option value="" @selected((string) $filtroTurma === '')>Todas</option>
                        @foreach ($turmas as $turma)
                            <option value="{{ $turma->id }}" @selected((string) $filtroTurma === (string) ($turma->id))>{{ $turma->nome }} · {{ $turma->periodo_letivo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Situação" para="filtro-status">
                    <x-select id="filtro-status" wire:model.live="filtroStatus">
                        <option value="" @selected((string) $filtroStatus === '')>Todas</option>
                        @foreach ($situacoes as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected((string) $filtroStatus === (string) ($valor))>{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\Aluno::class)
                <x-botao href="{{ route('alunos.criar') }}" wire:navigate class="shrink-0">Novo aluno</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($alunos->isNotEmpty())
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                A senha do aluno é redefinida na SED. Entre na SED neste mesmo navegador antes de usar o botão.
            </p>
        @endif

        @if ($alunos->isEmpty())
            <x-vazio titulo="Nenhum aluno encontrado"
                     :descricao="auth()->user()->ehGestao()
                         ? 'Matricule alunos em uma turma para começar.'
                         : 'Aparecem aqui os alunos das turmas em que você tem disciplina numa solicitação de prova.'"/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Aluno {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('ra')" class="font-semibold">RA {{ $this->setaDaColuna('ra') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Turma</th>
                            <th class="hidden px-4 py-2 md:table-cell">Curso</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($alunos as $aluno)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $aluno->nome }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $aluno->ra }}</td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    {{-- O professor consulta alunos, mas não abre a turma. --}}
                                    @can('view', $aluno->turma)
                                        <a href="{{ route('turmas.show', $aluno->turma) }}" wire:navigate
                                           class="text-marca-600 hover:underline dark:text-marca-400">{{ $aluno->turma->nome }}</a>
                                    @else
                                        {{ $aluno->turma->nome }}
                                    @endcan
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">{{ $aluno->turma->curso->nome }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$aluno->status->cor()" :rotulo="$aluno->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <div class="flex items-center justify-end gap-1">
                                        {{--
                                            Link comum, em outra aba, e não wire:navigate: a
                                            página é da SED, e é o navegador que leva a sessão
                                            que o professor já abriu lá.

                                            Sem Referer: vindo de outro site, a SED manda para
                                            a página inicial, e o mesmo endereço aberto direto
                                            funciona.
                                        --}}
                                        @can('redefinirSenhaNaSed', $aluno)
                                            @if ($aluno->linkDeResetDeSenhaNaSed() !== null)
                                                <x-botao variante="discreto" href="{{ $aluno->linkDeResetDeSenhaNaSed() }}"
                                                         target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"
                                                         title="Abre a SED em outra aba. Entre na SED neste navegador antes de clicar.">
                                                    Redefinir senha na SED
                                                </x-botao>
                                            @else
                                                <span class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500"
                                                      title="A coordenação cadastra o código da SED no formulário do aluno.">
                                                    Sem código da SED
                                                </span>
                                            @endif
                                        @endcan

                                        @can('update', $aluno)
                                            <x-botao variante="discreto" href="{{ route('alunos.editar', $aluno) }}" wire:navigate>Editar</x-botao>
                                        @endcan
                                        @can('delete', $aluno)
                                            <x-botao variante="discreto" class="text-rose-600 dark:text-rose-400" wire:click="pedirExclusao('aluno', {{ $aluno->id }})">Excluir</x-botao>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $alunos->links() }}</div>
        @endif
    </x-cartao>

    <x-confirmacao-de-exclusao :exclusao="$exclusao"/>
</div>
