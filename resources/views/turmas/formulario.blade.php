<div class="mx-auto max-w-xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados da turma">
            <div class="space-y-4">
                <x-campo rotulo="Curso" para="curso_id" obrigatorio :erro="$errors->first('curso_id')">
                    <x-select id="curso_id" wire:model.live="curso_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($cursosDisponiveis as $curso)
                            <option value="{{ $curso->id }}">{{ $curso->nome }} ({{ $curso->duracao_anos }} anos)</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Grade curricular" para="grade_curricular_id" obrigatorio
                         :erro="$errors->first('grade_curricular_id')"
                         ajuda="Esta versão fica congelada na turma: publicar uma grade nova depois não muda esta turma.">
                    @if ($gradesDisponiveis->isEmpty())
                        <x-alerta tipo="info">
                            Este curso ainda não tem grade publicada. Ao salvar, o sistema publica a
                            <strong>v1</strong> com as disciplinas cadastradas hoje e congela a turma nela.
                        </x-alerta>
                    @else
                        <x-select id="grade_curricular_id" wire:model="grade_curricular_id" required>
                            <option value="">Selecione…</option>
                            @foreach ($gradesDisponiveis as $grade)
                                <option value="{{ $grade->id }}">
                                    v{{ $grade->versao }} · {{ $grade->ano_vigencia }} · {{ $grade->status->rotulo() }}
                                </option>
                            @endforeach
                        </x-select>
                    @endif
                </x-campo>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-campo rotulo="Período" para="periodo" obrigatorio :erro="$errors->first('periodo')"
                             ajuda="Ano do curso que a turma cursa.">
                        <x-select id="periodo" wire:model="periodo" required>
                            <option value="1">1º período</option>
                            <option value="2">2º período</option>
                            <option value="3">3º período</option>
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Nome da turma" para="nome" obrigatorio
                             :erro="$errors->first('nome')" ajuda="Ex.: 2 A, 3B, Noturno A">
                        <x-input id="nome" wire:model="nome" required placeholder="2 A"/>
                    </x-campo>

                    <x-campo rotulo="Período letivo" para="periodo_letivo" obrigatorio :erro="$errors->first('periodo_letivo')">
                        <x-input id="periodo_letivo" wire:model="periodo_letivo" required/>
                    </x-campo>
                </div>

                <x-campo rotulo="Status" para="status" obrigatorio :erro="$errors->first('status')">
                    <x-select id="status" wire:model="status" required>
                        @foreach ($situacoes as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('turmas.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar</x-botao>
        </div>
    </form>
</div>
