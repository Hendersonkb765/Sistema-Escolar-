<div class="mx-auto max-w-xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados da disciplina"
                  descricao="A disciplina pertence a um curso e é cursada no período que você indicar.">
            <div class="space-y-4">
                <x-campo rotulo="Curso" para="curso_id" obrigatorio :erro="$errors->first('curso_id')">
                    <x-select id="curso_id" wire:model.live="curso_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($cursosDisponiveis as $curso)
                            <option value="{{ $curso->id }}">{{ $curso->nome }} ({{ $curso->duracao_anos }} anos)</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Nome" para="nome" obrigatorio :erro="$errors->first('nome')">
                    <x-input id="nome" wire:model="nome" required placeholder="Ex.: Lógica de Programação"/>
                </x-campo>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-campo rotulo="Código" para="codigo" obrigatorio :erro="$errors->first('codigo')"
                             ajuda="Único no curso.">
                        <x-input id="codigo" wire:model="codigo" required placeholder="LOG"/>
                    </x-campo>

                    <x-campo rotulo="Período" para="periodo" obrigatorio :erro="$errors->first('periodo')"
                             ajuda="Ano do curso.">
                        <x-select id="periodo" wire:model="periodo" required>
                            @for ($p = 1; $p <= $duracao; $p++)
                                <option value="{{ $p }}">{{ $p }}º período</option>
                            @endfor
                        </x-select>
                    </x-campo>

                    <x-campo rotulo="Carga horária" para="carga_horaria" obrigatorio
                             :erro="$errors->first('carga_horaria')">
                        <x-input tipo="number" id="carga_horaria" wire:model="carga_horaria" min="1" required/>
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

            @if ($disciplina !== null)
                <x-alerta tipo="info" class="mt-4">
                    Mudar o período aqui não altera turmas já abertas: cada uma segue na versão de grade
                    que congelou. Publique uma nova versão no curso para que a mudança valha nas próximas.
                </x-alerta>
            @endif
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('disciplinas.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar</x-botao>
        </div>
    </form>
</div>
