<div class="mx-auto max-w-4xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="A prova">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Turma" para="turma_id" obrigatorio :erro="$errors->first('turma_id')">
                        <x-select id="turma_id" wire:model.live="turma_id" required>
                            <option value="">Selecione…</option>
                            @foreach ($turmas as $turma)
                                <option value="{{ $turma->id }}">
                                    {{ $turma->nome }} · {{ $turma->curso->nome }} · {{ $turma->periodo }}º período
                                </option>
                            @endforeach
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Bimestre" para="bimestre" obrigatorio :erro="$errors->first('bimestre')"
                             ajuda="O ano letivo tem quatro.">
                        <x-select id="bimestre" wire:model="bimestre" required>
                            @foreach ($bimestres as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </x-campo>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Título da prova (opcional)" para="titulo" :erro="$errors->first('titulo')"
                             ajuda="Ex.: Avaliação do 2º bimestre.">
                        <x-input id="titulo" wire:model="titulo" placeholder="Avaliação do 2º bimestre"/>
                    </x-campo>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Prazo de entrega" para="prazo" obrigatorio :erro="$errors->first('prazo')"
                             ajuda="Vale para todos os professores. Vencer o prazo não bloqueia o envio.">
                        <x-input tipo="datetime-local" id="prazo" wire:model="prazo" required/>
                    </x-campo>

                    <x-campo rotulo="Alternativas por questão" para="quantidade_alternativas" obrigatorio
                             :erro="$errors->first('quantidade_alternativas')"
                             ajuda="Vale para a prova inteira.">
                        <x-select id="quantidade_alternativas" wire:model="quantidade_alternativas" required>
                            @for ($n = 2; $n <= 6; $n++)
                                <option value="{{ $n }}">{{ $n }} alternativas</option>
                            @endfor
                        </x-select>
                    </x-campo>
                </div>

                <x-campo rotulo="Observações gerais" para="observacoes" :erro="$errors->first('observacoes')"
                         ajuda="Todos os professores veem este texto.">
                    <x-area-texto id="observacoes" wire:model="observacoes" :linhas="2"
                                  placeholder="Ex.: priorize conteúdo do segundo bimestre"/>
                </x-campo>
            </div>
        </x-cartao>

        <x-cartao titulo="Disciplinas e professores"
                  :descricao="'A prova reúne as disciplinas abaixo — '.$totalDeQuestoes.' questão(ões) no total.'">
            <x-slot:acoes>
                <x-botao variante="secundario" type="button" wire:click="adicionarParte"
                         :desabilitado="$turmaSelecionada === null">
                    + Adicionar disciplina
                </x-botao>
            </x-slot:acoes>

            @error('partes')
                <x-alerta tipo="erro" class="mb-3">{{ $message }}</x-alerta>
            @enderror

            @if ($turmaSelecionada === null)
                <x-alerta tipo="info">Escolha a turma primeiro: as disciplinas oferecidas são as que ela cursa.</x-alerta>
            @elseif ($disciplinas->isEmpty())
                <x-alerta tipo="atencao">
                    A grade congelada nesta turma não tem disciplinas no {{ $turmaSelecionada->periodo }}º período.
                </x-alerta>
            @else
                <div class="space-y-3">
                    @foreach ($partes as $indice => $parte)
                        @php
                            $disciplinaEscolhida = (int) ($parte['disciplina_id'] ?: 0);
                            $sugeridos = $sugeridosPorDisciplina[$disciplinaEscolhida] ?? collect();
                            $idsSugeridos = $sugeridos->pluck('id')->all();
                        @endphp

                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                             wire:key="parte-{{ $indice }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $indice + 1 }}
                                </span>

                                @if (count($partes) > 1)
                                    <x-botao variante="discreto" type="button"
                                             wire:click="removerParte({{ $indice }})"
                                             class="text-rose-600 dark:text-rose-400">
                                        Remover
                                    </x-botao>
                                @endif
                            </div>

                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-12">
                                <x-campo rotulo="Disciplina" obrigatorio class="sm:col-span-5"
                                         :erro="$errors->first('partes.'.$indice.'.disciplina_id')">
                                    <x-select wire:model.live="partes.{{ $indice }}.disciplina_id" required>
                                        <option value="">Selecione…</option>
                                        @foreach ($disciplinas as $disciplina)
                                            <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                                        @endforeach
                                    </x-select>
                                </x-campo>

                                <x-campo rotulo="Professor responsável" obrigatorio class="sm:col-span-5"
                                         :erro="$errors->first('partes.'.$indice.'.professor_id')">
                                    <x-select wire:model="partes.{{ $indice }}.professor_id" required>
                                        <option value="">Selecione…</option>
                                        @if ($sugeridos->isNotEmpty())
                                            <optgroup label="Já leciona esta disciplina">
                                                @foreach ($sugeridos as $usuario)
                                                    <option value="{{ $usuario->id }}">{{ $usuario->nome }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="Outras contas do seu escopo">
                                            @foreach ($outros as $usuario)
                                                @unless (in_array($usuario->id, $idsSugeridos, true))
                                                    <option value="{{ $usuario->id }}">
                                                        {{ $usuario->nome }} ({{ $usuario->perfil->rotulo() }})
                                                    </option>
                                                @endunless
                                            @endforeach
                                        </optgroup>
                                    </x-select>
                                </x-campo>

                                <x-campo rotulo="Questões" obrigatorio class="sm:col-span-2"
                                         :erro="$errors->first('partes.'.$indice.'.quantidade_questoes')">
                                    <x-input tipo="number" min="1" max="60"
                                             wire:model.live.debounce.500ms="partes.{{ $indice }}.quantidade_questoes"/>
                                </x-campo>
                            </div>

                            <x-campo rotulo="Orientação para este professor (opcional)" class="mt-2"
                                     :erro="$errors->first('partes.'.$indice.'.observacoes')">
                                <x-input wire:model="partes.{{ $indice }}.observacoes"
                                         placeholder="Ex.: foque em estruturas de repetição"/>
                            </x-campo>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Cada professor recebe apenas a parte dele e entrega quando terminar, sem esperar
                    pelos outros. O peso de cada questão é definido por quem a escreve.
                </p>
            @endif
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('solicitacoes.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Abrir solicitação</x-botao>
        </div>
    </form>
</div>
