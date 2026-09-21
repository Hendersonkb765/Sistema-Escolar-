<div class="mx-auto max-w-xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados da disciplina"
                  descricao="O ano em que a disciplina é cursada é definido na grade curricular, não aqui.">
            <div class="space-y-4">
                <x-campo rotulo="Eixo" para="eixo_id" obrigatorio :erro="$errors->first('eixo_id')">
                    <x-select id="eixo_id" wire:model="eixo_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($eixosDisponiveis as $eixo)
                            <option value="{{ $eixo->id }}">{{ $eixo->nome }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Nome" para="nome" obrigatorio :erro="$errors->first('nome')">
                    <x-input id="nome" wire:model="nome" required/>
                </x-campo>

                <x-campo rotulo="Código" para="codigo" obrigatorio :erro="$errors->first('codigo')"
                         ajuda="Único dentro do eixo, ex.: LOG.">
                    <x-input id="codigo" wire:model="codigo" required/>
                </x-campo>

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
            <x-botao variante="secundario" href="{{ route('disciplinas.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar</x-botao>
        </div>
    </form>
</div>
