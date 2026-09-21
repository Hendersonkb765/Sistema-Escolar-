<x-layouts.auth titulo="Confirmar senha">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Confirme sua senha</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Esta é uma área protegida. Confirme sua senha para continuar.</p>

    @error('password')
        <x-alerta tipo="erro" class="mt-4">{{ $message }}</x-alerta>
    @enderror

    <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-campo rotulo="Senha" para="password" obrigatorio>
            <x-input tipo="password" id="password" name="password" required autofocus autocomplete="current-password"/>
        </x-campo>

        <x-botao tipo="submit" class="w-full">Confirmar</x-botao>
    </form>
</x-layouts.auth>
