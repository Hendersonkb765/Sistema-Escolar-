<div class="space-y-4">
    <x-cartao>
        <x-slot:acoes></x-slot:acoes>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou e-mail"/>
                </x-campo>

                <x-campo rotulo="Perfil" para="filtro-perfil">
                    <x-select id="filtro-perfil" wire:model.live="filtroPerfil">
                        <option value="">Todos</option>
                        @foreach ($perfis as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Situação" para="filtro-situacao">
                    <x-select id="filtro-situacao" wire:model.live="filtroSituacao">
                        <option value="">Todas</option>
                        <option value="ativos">Ativos</option>
                        <option value="inativos">Inativos</option>
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\User::class)
                <x-botao href="{{ route('usuarios.criar') }}" wire:navigate class="shrink-0">
                    Novo usuário
                </x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao class="overflow-hidden" :descricao="$usuarios->total().' conta(s) no seu escopo'">
        @if ($usuarios->isEmpty())
            <x-vazio titulo="Nenhum usuário encontrado"
                     descricao="Ajuste a busca ou os filtros, ou cadastre uma nova conta."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th scope="col" class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold hover:text-slate-800 dark:hover:text-slate-200">
                                    Nome {{ $this->setaDaColuna('nome') }}
                                </button>
                            </th>
                            <th scope="col" class="hidden px-4 py-2 md:table-cell">
                                <button type="button" wire:click="ordenar('email')" class="font-semibold hover:text-slate-800 dark:hover:text-slate-200">
                                    E-mail {{ $this->setaDaColuna('email') }}
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-2">
                                <button type="button" wire:click="ordenar('perfil')" class="font-semibold hover:text-slate-800 dark:hover:text-slate-200">
                                    Perfil {{ $this->setaDaColuna('perfil') }}
                                </button>
                            </th>
                            <th scope="col" class="hidden px-4 py-2 lg:table-cell">Eixos</th>
                            <th scope="col" class="px-4 py-2">Situação</th>
                            <th scope="col" class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($usuarios as $linha)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 sm:px-6">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $linha->nome }}</p>
                                    <p class="text-xs text-slate-500 md:hidden dark:text-slate-400">{{ $linha->email }}</p>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">{{ $linha->email }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$linha->perfil->cor()" :rotulo="$linha->perfil->rotulo()"/>
                                </td>
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    @forelse ($linha->eixos as $eixo)
                                        <x-badge cor="cinza" class="mr-1">{{ $eixo->nome }}</x-badge>
                                    @empty
                                        <span class="text-xs text-slate-400">—</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$linha->ativo ? 'verde' : 'cinza'"
                                             :rotulo="$linha->ativo ? 'Ativo' : 'Inativo'"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $linha)
                                            <x-botao variante="discreto" href="{{ route('usuarios.editar', $linha) }}" wire:navigate>
                                                Editar
                                            </x-botao>
                                        @endcan

                                        @can('alternarAtivacao', $linha)
                                            @if ($confirmandoDesativacao === $linha->id)
                                                <x-botao variante="perigo" wire:click="alternarAtivacao({{ $linha->id }})">
                                                    Confirmar
                                                </x-botao>
                                                <x-botao variante="discreto" wire:click="cancelarConfirmacao">Cancelar</x-botao>
                                            @elseif ($linha->ativo)
                                                <x-botao variante="discreto" wire:click="confirmarDesativacao({{ $linha->id }})">
                                                    Desativar
                                                </x-botao>
                                            @else
                                                <x-botao variante="secundario" wire:click="alternarAtivacao({{ $linha->id }})">
                                                    Reativar
                                                </x-botao>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $usuarios->links() }}</div>
        @endif
    </x-cartao>
</div>
