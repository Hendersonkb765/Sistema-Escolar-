<div class="mx-auto max-w-4xl">
    <form wire:submit="salvar" class="space-y-4">
        @if ($grade === null)
            <x-alerta tipo="info">
                A grade nasce como <strong>rascunho</strong>. Ela só passa a valer para novas turmas
                depois de publicada.
            </x-alerta>
        @endif

        <x-cartao titulo="Identificação">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-campo rotulo="Curso" para="curso_id" obrigatorio :erro="$errors->first('curso_id')" class="sm:col-span-2">
                    <x-select id="curso_id" wire:model.live="curso_id" required :disabled="$grade !== null">
                        <option value="">Selecione…</option>
                        @foreach ($cursosDisponiveis as $curso)
                            <option value="{{ $curso->id }}">{{ $curso->nome }} ({{ $curso->duracao_anos }} anos)</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Ano de vigência" para="ano_vigencia" obrigatorio :erro="$errors->first('ano_vigencia')">
                    <x-input tipo="number" id="ano_vigencia" wire:model="ano_vigencia" required/>
                </x-campo>
            </div>

            <x-campo rotulo="Observações" para="observacoes" class="mt-4" :erro="$errors->first('observacoes')"
                     ajuda="Útil para registrar o que mudou em relação à versão anterior.">
                <textarea id="observacoes" wire:model="observacoes" rows="2"
                          class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></textarea>
            </x-campo>
        </x-cartao>

        <x-cartao titulo="Disciplinas da grade"
                  descricao="Cada linha posiciona uma disciplina em um ano do curso. A mesma disciplina pode repetir em anos diferentes.">
            <x-slot:acoes>
                <x-botao variante="secundario" wire:click="adicionarItem" type="button">Adicionar disciplina</x-botao>
            </x-slot:acoes>

            @error('itens')
                <x-alerta tipo="erro" class="mb-3">{{ $message }}</x-alerta>
            @enderror

            @if ($itens === [])
                <x-vazio titulo="Grade vazia"
                         descricao="Adicione ao menos uma disciplina antes de salvar."/>
            @else
                <div class="space-y-3">
                    @foreach ($itens as $indice => $item)
                        <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-12 dark:border-slate-800"
                             wire:key="item-{{ $indice }}">
                            <x-campo rotulo="Disciplina" class="sm:col-span-6"
                                     :erro="$errors->first('itens.'.$indice.'.disciplina_id')">
                                <x-select wire:model="itens.{{ $indice }}.disciplina_id">
                                    <option value="">Selecione…</option>
                                    @foreach ($disciplinasDisponiveis as $disciplina)
                                        <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                                    @endforeach
                                </x-select>
                            </x-campo>

                            <x-campo rotulo="Ano" class="sm:col-span-2"
                                     :erro="$errors->first('itens.'.$indice.'.ano_curso')">
                                <x-select wire:model="itens.{{ $indice }}.ano_curso">
                                    @for ($ano = 1; $ano <= $duracao; $ano++)
                                        <option value="{{ $ano }}">{{ $ano }}º</option>
                                    @endfor
                                </x-select>
                            </x-campo>

                            <x-campo rotulo="Carga horária" class="sm:col-span-3"
                                     :erro="$errors->first('itens.'.$indice.'.carga_horaria')">
                                <x-input tipo="number" wire:model="itens.{{ $indice }}.carga_horaria" min="1"/>
                            </x-campo>

                            <div class="flex items-end sm:col-span-1">
                                <x-botao variante="discreto" type="button"
                                         wire:click="removerItem({{ $indice }})"
                                         class="w-full text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                    Remover
                                </x-botao>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('grades.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar rascunho</x-botao>
        </div>
    </form>
</div>
