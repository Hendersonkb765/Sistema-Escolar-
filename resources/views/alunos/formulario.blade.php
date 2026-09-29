<div class="mx-auto max-w-2xl space-y-4">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados do aluno">
            <div class="space-y-4">
                <x-campo rotulo="Turma" para="turma_id" obrigatorio :erro="$errors->first('turma_id')"
                         ajuda="Mudar a turma aqui registra a movimentação no histórico do aluno.">
                    <x-select id="turma_id" wire:model.live="turma_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($turmasDisponiveis as $turma)
                            <option value="{{ $turma->id }}">
                                {{ $turma->nome }} · {{ $turma->curso->nome }} · {{ $turma->periodo_letivo }}
                            </option>
                        @endforeach
                    </x-select>
                </x-campo>

                @if ($trocandoDeTurma)
                    <x-alerta tipo="atencao" titulo="Troca de turma">
                        O aluno sairá da turma atual. A movimentação fica registrada no histórico, com o motivo abaixo.
                    </x-alerta>

                    <x-campo rotulo="Motivo da movimentação" para="motivo" :erro="$errors->first('motivoMovimentacao')">
                        <x-input id="motivo" wire:model="motivoMovimentacao" placeholder="Ex.: transferência a pedido"/>
                    </x-campo>
                @endif

                <x-campo rotulo="Nome completo" para="nome" obrigatorio :erro="$errors->first('nome')">
                    <x-input id="nome" wire:model="nome" required/>
                </x-campo>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="RA" para="ra" obrigatorio :erro="$errors->first('ra')"
                             ajuda="Única dentro da turma.">
                        <x-input id="ra" wire:model="ra" required/>
                    </x-campo>

                    <x-campo rotulo="Status" para="status" obrigatorio :erro="$errors->first('status')">
                        <x-select id="status" wire:model="status" required>
                            @foreach ($situacoes as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>
                </div>
            </div>
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('alunos.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar</x-botao>
        </div>
    </form>

    @if ($historicos->isNotEmpty())
        <x-cartao titulo="Histórico do aluno" descricao="Nada aqui é sobrescrito ou apagado.">
            <ol class="space-y-3">
                @foreach ($historicos as $registro)
                    <li class="flex gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-slate-800">
                        <div class="mt-1 shrink-0">
                            <x-badge :cor="$registro->evento->cor()" :rotulo="$registro->evento->rotulo()"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700 dark:text-slate-200">
                                @if ($registro->turmaAnterior && $registro->turmaNova)
                                    {{ $registro->turmaAnterior->nome }} → {{ $registro->turmaNova->nome }}
                                @elseif ($registro->turmaNova)
                                    Turma {{ $registro->turmaNova->nome }}
                                @endif
                                @if ($registro->status_anterior !== $registro->status_novo)
                                    · status {{ $registro->status_anterior ?? '—' }} → {{ $registro->status_novo }}
                                @endif
                            </p>
                            @if ($registro->motivo)
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $registro->motivo }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $registro->created_at?->format('d/m/Y H:i') }}
                                @if ($registro->registradoPor) · {{ $registro->registradoPor->nome }} @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-cartao>
    @endif
</div>
