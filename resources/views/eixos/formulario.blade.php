<div class="mx-auto max-w-xl">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados do eixo">
            <div class="space-y-4">
                <x-campo rotulo="Nome" para="nome" obrigatorio :erro="$errors->first('nome')">
                    <x-input id="nome" wire:model="nome" required aria-invalid="{{ $errors->has('nome') ? 'true' : 'false' }}"/>
                </x-campo>

                <x-campo rotulo="Código" para="codigo" obrigatorio :erro="$errors->first('codigo')"
                         ajuda="Identificador curto e único, ex.: TEC.">
                    <x-input id="codigo" wire:model="codigo" required aria-invalid="{{ $errors->has('codigo') ? 'true' : 'false' }}"/>
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
            <x-botao variante="secundario" href="{{ route('eixos.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">Salvar</x-botao>
        </div>
    </form>
</div>
