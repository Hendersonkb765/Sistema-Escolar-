<x-layouts.auth titulo="Entrar">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Entrar</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use as credenciais fornecidas pela coordenação.</p>

    @if (session('status'))
        <x-alerta tipo="info" class="mt-4">{{ session('status') }}</x-alerta>
    @endif

    @if ($errors->any())
        <x-alerta tipo="erro" class="mt-4">
            <ul class="space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </x-alerta>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-campo rotulo="E-mail" para="email" obrigatorio>
            <x-input tipo="email" id="email" name="email" value="{{ old('email') }}"
                     required autofocus autocomplete="username"
                     aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"/>
        </x-campo>

        <x-campo rotulo="Senha" para="password" obrigatorio>
            <x-input tipo="password" id="password" name="password" required autocomplete="current-password"
                     aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"/>
        </x-campo>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="remember"
                       class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                Lembrar-me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-marca-600 hover:text-marca-700 dark:text-marca-400">
                    Esqueci minha senha
                </a>
            @endif
        </div>

        <x-botao tipo="submit" class="w-full">Entrar</x-botao>
    </form>
</x-layouts.auth>
