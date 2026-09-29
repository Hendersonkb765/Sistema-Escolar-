<div class="space-y-4">
    @if ($aguardando > 0)
        <x-alerta tipo="atencao">
            {{ $aguardando === 1
                ? 'Um PAEET compartilhou um modelo com você e está esperando resposta.'
                : "{$aguardando} modelos compartilhados com você estão esperando resposta." }}
            <a href="{{ route('documentos.compartilhados') }}" wire:navigate class="font-semibold underline">
                Ver agora
            </a>
        </x-alerta>
    @endif

    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou descrição"/>
                </x-campo>

                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model.live="mostrarInativos"
                           class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                    Mostrar também os desativados
                </label>
            </div>

            <div class="flex shrink-0 gap-2">
                <x-botao variante="secundario" href="{{ route('documentos.gerar') }}" wire:navigate>
                    Gerar documentos
                </x-botao>
                @can('create', App\Models\ModeloDocumento::class)
                    <x-botao href="{{ route('documentos.criar') }}" wire:navigate>Novo modelo</x-botao>
                @endcan
            </div>
        </div>
    </x-cartao>

    <x-cartao>
        @if ($modelos->isEmpty())
            <x-vazio titulo="Nenhum modelo de documento"
                     descricao="Um modelo é o texto que vira autorização, declaração ou ficha, com os dados de cada aluno preenchidos e as linhas em branco para assinar.">
                <x-slot:acoes>
                    @can('create', App\Models\ModeloDocumento::class)
                        <x-botao href="{{ route('documentos.criar') }}" wire:navigate>Novo modelo</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{-- A largura é dada aqui porque a tabela se
                                 distribui sozinha: sem isso o nome do modelo
                                 quebrava em três linhas enquanto a coluna do
                                 autor sobrava. --}}
                            <th class="w-2/5 px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">
                                    Modelo {{ $this->setaDaColuna('nome') }}
                                </button>
                            </th>
                            <th class="hidden px-4 py-2 md:table-cell">Formato</th>
                            <th class="hidden px-4 py-2 lg:table-cell">Autor</th>
                            <th class="px-4 py-2">Situação</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($modelos as $modelo)
                            <tr wire:key="modelo-{{ $modelo->id }}">
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $modelo->nome }}</span>
                                    @if ($modelo->descricao)
                                        <span class="block text-xs text-slate-400">{{ $modelo->descricao }}</span>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 md:table-cell">
                                    <x-badge :cor="$modelo->tipo->cor()" :rotulo="$modelo->tipo->rotulo()"/>
                                    @if ($modelo->ehIndividual() && $modelo->por_pagina > 1)
                                        <span class="block pt-1 text-xs text-slate-400">
                                            {{ $modelo->por_pagina }} por página
                                        </span>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 lg:table-cell dark:text-slate-300">
                                    {{ $modelo->autor?->nome ?? '—' }}
                                    @if ($modelo->original)
                                        <span class="block text-xs text-slate-400">
                                            cópia de um modelo de {{ $modelo->original->autor?->nome ?? 'outro PAEET' }}
                                        </span>
                                    @endif
                                    @if ($modelo->compartilhamentos_count > 0)
                                        <span class="block pt-1 text-xs tabular-nums text-slate-400">
                                            compartilhado: {{ $modelo->aceitos_count }} de {{ $modelo->compartilhamentos_count }} aceitaram
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-badge :cor="$modelo->ativo ? 'verde' : 'cinza'"
                                             :rotulo="$modelo->ativo ? 'Ativo' : 'Desativado'"/>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-botao variante="discreto"
                                                 href="{{ route('documentos.gerar', ['modelo' => $modelo->id]) }}"
                                                 wire:navigate title="Escolher a turma e os alunos que recebem este documento.">
                                            Gerar
                                        </x-botao>
                                        @can('compartilhar', $modelo)
                                            <x-botao variante="discreto"
                                                     wire:click="abrirCompartilhamento({{ $modelo->id }})"
                                                     title="Oferecer este modelo a outro PAEET. Ele entra na lista dele só se aceitar.">
                                                Compartilhar
                                            </x-botao>
                                        @endcan
                                        @can('update', $modelo)
                                            <x-botao variante="discreto" wire:click="alternarAtivo({{ $modelo->id }})"
                                                     title="{{ $modelo->ativo
                                                        ? 'Some da geração; os documentos já gerados seguem válidos.'
                                                        : 'Volta a aparecer na geração de documentos.' }}">
                                                {{ $modelo->ativo ? 'Desativar' : 'Reativar' }}
                                            </x-botao>
                                            <x-botao variante="secundario"
                                                     href="{{ route('documentos.editar', $modelo) }}" wire:navigate>
                                                Editar
                                            </x-botao>
                                        @endcan
                                    </div>
                                </td>
                            </tr>

                            @if ($compartilhando === $modelo->id)
                                <tr wire:key="compartilhar-{{ $modelo->id }}">
                                    <td colspan="5" class="bg-slate-50 px-4 py-4 sm:px-6 dark:bg-slate-950/40">
                                        <p class="mb-3 text-sm font-medium text-slate-900 dark:text-slate-100">
                                            Compartilhar “{{ $modelo->nome }}”
                                        </p>
                                        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                            O PAEET escolhido recebe um convite. Se aceitar, ganha uma cópia própria,
                                            que pode editar sem alterar este modelo. Se recusar, nada é criado.
                                        </p>

                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-campo rotulo="Enviar para" para="destinatario_id" obrigatorio
                                                     :erro="$errors->first('destinatario_id')">
                                                <x-select id="destinatario_id" wire:model="destinatario_id">
                                                    <option value="">Escolha um PAEET</option>
                                                    @foreach ($destinatarios as $pessoa)
                                                        <option value="{{ $pessoa->id }}">
                                                            {{ $pessoa->nome }} — {{ $pessoa->email }}
                                                        </option>
                                                    @endforeach
                                                </x-select>
                                            </x-campo>

                                            <x-campo rotulo="Recado (opcional)" para="mensagem"
                                                     :erro="$errors->first('mensagem')">
                                                <x-input id="mensagem" wire:model="mensagem"
                                                         placeholder="Para que serve este modelo"/>
                                            </x-campo>
                                        </div>

                                        <div class="mt-3 flex gap-2">
                                            <x-botao wire:click="compartilhar">Enviar convite</x-botao>
                                            <x-botao variante="secundario" wire:click="fecharCompartilhamento">
                                                Cancelar
                                            </x-botao>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $modelos->links() }}</div>
        @endif
    </x-cartao>
</div>
