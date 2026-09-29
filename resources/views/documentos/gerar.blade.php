<div class="space-y-4">
    <x-cartao titulo="O que gerar">
        <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">
            <x-campo rotulo="Modelo do documento" para="modelo_id" obrigatorio>
                <x-select id="modelo_id" wire:model.live="modelo_id">
                    <option value="">Escolha um modelo</option>
                    @foreach ($modelos as $opcao)
                        <option value="{{ $opcao->id }}">{{ $opcao->nome }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Turma" para="turma_id" obrigatorio>
                <x-select id="turma_id" wire:model.live="turma_id">
                    <option value="">Escolha uma turma</option>
                    @foreach ($turmas as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->nome }} — {{ $opcao->curso->nome }} ({{ $opcao->periodo_letivo }})
                        </option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>

        @if ($modelo)
            <div class="border-t border-slate-200 px-4 py-3 sm:px-6 dark:border-slate-800">
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <x-badge :cor="$modelo->tipo->cor()" :rotulo="$modelo->tipo->rotulo()"/>
                    @if ($modelo->ehIndividual())
                        <span>
                            {{ $modelo->por_pagina === 1
                                ? 'Uma folha por aluno.'
                                : $modelo->por_pagina.' vias por folha, com linha de corte entre elas.' }}
                        </span>
                    @else
                        <span>Uma folha só, com a relação dos alunos marcados.</span>
                    @endif
                </div>
            </div>
        @endif
    </x-cartao>

    <x-cartao>
        <x-slot:titulo>Quem recebe o documento</x-slot:titulo>
        <x-slot:descricao>
            {{ $alunos->isEmpty()
                ? 'Escolha uma turma para ver os alunos.'
                : $totalEscolhidos.' de '.$alunos->count().' alunos marcados.' }}
        </x-slot:descricao>

        <x-slot:acoes>
            @if ($alunos->isNotEmpty())
                <x-botao variante="discreto" wire:click="marcarTodos">Marcar todos</x-botao>
                <x-botao variante="discreto" wire:click="desmarcarTodos">Desmarcar todos</x-botao>
            @endif
        </x-slot:acoes>

        <div class="p-4 sm:p-6">
            @if ($alunos->isEmpty())
                <x-vazio titulo="Nenhum aluno nesta turma"
                         descricao="Matricule alunos na turma para gerar documentos para eles."/>
            @else
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($alunos as $aluno)
                        <label wire:key="aluno-{{ $aluno->id }}"
                               class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <input type="checkbox" value="{{ $aluno->id }}" wire:model.live="escolhidos"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            <span class="min-w-0">
                                <span class="block truncate text-slate-900 dark:text-slate-100">{{ $aluno->nome }}</span>
                                <span class="block text-xs text-slate-400">RA {{ $aluno->ra }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </x-cartao>

    <x-cartao>
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $impedimento !== ''
                    ? $impedimento
                    : 'O arquivo é baixado na hora. O Word serve para mexer no texto antes de imprimir.' }}
            </p>

            <div class="flex shrink-0 gap-2">
                <x-botao variante="secundario" wire:click="gerar('word')"
                         :desabilitado="$impedimento !== ''"
                         title="{{ $impedimento !== ''
                            ? $impedimento
                            : 'Gerar e baixar em Word (.docx), para editar antes de imprimir.' }}">
                    Criar em Word
                </x-botao>

                <x-botao wire:click="gerar('pdf')" :desabilitado="$impedimento !== ''"
                         title="{{ $impedimento !== '' ? $impedimento : 'Gerar e baixar o PDF, pronto para imprimir.' }}">
                    Criar em PDF
                </x-botao>
            </div>
        </div>
    </x-cartao>
</div>
