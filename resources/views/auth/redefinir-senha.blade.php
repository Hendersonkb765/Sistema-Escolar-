<x-layouts.auth titulo="Definir nova senha">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Definir nova senha</h2>

    @if ($errors->any())
        <x-alerta tipo="erro" class="mt-4">
            <ul class="space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </x-alerta>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-campo rotulo="E-mail" para="email" obrigatorio>
            <x-input tipo="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"/>
        </x-campo>

        <x-campo rotulo="Nova senha" para="password" obrigatorio>
            <x-input tipo="password" id="password" name="password" required autocomplete="new-password"/>
        </x-campo>

        <x-campo rotulo="Confirmar nova senha" para="password_confirmation" obrigatorio>
            <x-input tipo="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"/>
        </x-campo>

        <x-botao tipo="submit" class="w-full">Redefinir senha</x-botao>
    </form>
</x-layouts.auth>
