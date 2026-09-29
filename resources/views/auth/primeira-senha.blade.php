<div>
    <form wire:submit="definir" class="space-y-4">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            A senha que você usou para entrar foi definida por quem criou a sua conta,
            e portanto outra pessoa a conhece. Escolha agora a sua — é ela que valerá
            daqui em diante.
        </div>

        <x-campo rotulo="Nova senha" para="senha" obrigatorio :erro="$errors->first('senha')"
                 ajuda="Ao menos 8 caracteres.">
            <x-input tipo="password" id="senha" wire:model="senha" required
                     autocomplete="new-password" autofocus/>
        </x-campo>

        <x-campo rotulo="Repita a senha" para="senha_confirmation" obrigatorio>
            <x-input tipo="password" id="senha_confirmation" wire:model="senha_confirmation" required
                     autocomplete="new-password"/>
        </x-campo>

        <x-botao tipo="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="definir">Definir senha e entrar</span>
            <span wire:loading wire:target="definir">Definindo…</span>
        </x-botao>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs text-slate-500 underline hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            Prefiro sair e voltar depois
        </button>
    </form>
</div>
