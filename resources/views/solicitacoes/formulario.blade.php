<div class="mx-auto max-w-3xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Para quem e sobre o quê">
            <div class="space-y-4">
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

                <x-campo rotulo="Disciplina" para="disciplina_id" obrigatorio
                         :erro="$errors->first('disciplina_id')"
                         ajuda="Apenas as disciplinas que esta turma cursa no período dela.">
                    @if ($turmaSelecionada === null)
                        <x-alerta tipo="info">Escolha a turma primeiro.</x-alerta>
                    @elseif ($disciplinas->isEmpty())
                        <x-alerta tipo="atencao">
                            A grade congelada nesta turma não tem disciplinas no {{ $turmaSelecionada->periodo }}º período.
                        </x-alerta>
                    @else
                        <x-select id="disciplina_id" wire:model.live="disciplina_id" required>
                            <option value="">Selecione…</option>
                            @foreach ($disciplinas as $disciplina)
                                <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                            @endforeach
                        </x-select>
                    @endif
                </x-campo>

                <x-campo rotulo="Professor" para="professor_id" obrigatorio :erro="$errors->first('professor_id')"
                         ajuda="Designar alguém já cria o vínculo docente com a disciplina.">
                    <x-select id="professor_id" wire:model="professor_id" required>
                        <option value="">Selecione…</option>
                        @if ($sugeridos->isNotEmpty())
                            <optgroup label="Já leciona esta disciplina">
                                @foreach ($sugeridos as $usuario)
                                    <option value="{{ $usuario->id }}">{{ $usuario->nome }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($outros->isNotEmpty())
                            <optgroup label="Outras contas do seu escopo">
                                @foreach ($outros as $usuario)
                                    <option value="{{ $usuario->id }}">{{ $usuario->nome }} ({{ $usuario->perfil->rotulo() }})</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </x-select>
                </x-campo>
            </div>
        </x-cartao>

        <x-cartao titulo="Prazo e formato">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-campo rotulo="Prazo" para="prazo" obrigatorio :erro="$errors->first('prazo')"
                         ajuda="Vencer o prazo não bloqueia o envio.">
                    <x-input tipo="datetime-local" id="prazo" wire:model="prazo" required/>
                </x-campo>

                <x-campo rotulo="Questões" para="quantidade_questoes" obrigatorio
                         :erro="$errors->first('quantidade_questoes')">
                    <x-input tipo="number" id="quantidade_questoes" wire:model.live.debounce.500ms="quantidade_questoes"
                             min="1" max="60" required/>
                </x-campo>

                <x-campo rotulo="Alternativas por questão" para="quantidade_alternativas" obrigatorio
                         :erro="$errors->first('quantidade_alternativas')">
                    <x-select id="quantidade_alternativas" wire:model="quantidade_alternativas" required>
                        @for ($n = 2; $n <= 6; $n++)
                            <option value="{{ $n }}">{{ $n }} alternativas</option>
                        @endfor
                    </x-select>
                </x-campo>
            </div>

            <x-campo rotulo="Observações ao professor" para="observacoes" class="mt-4"
                     :erro="$errors->first('observacoes')">
                <x-area-texto id="observacoes" wire:model="observacoes" :linhas="2"
                              placeholder="Ex.: priorize conteúdo do segundo bimestre"/>
            </x-campo>
        </x-cartao>

        <x-alerta tipo="info">
            O <strong>peso</strong> de cada questão é definido pelo professor ao escrevê-la — é ele
            quem sabe quanto cada uma vale dentro da disciplina.
        </x-alerta>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('solicitacoes.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Abrir solicitação</x-botao>
        </div>
    </form>
</div>
