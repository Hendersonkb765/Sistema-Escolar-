<x-layouts.auth titulo="Recuperar senha">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Recuperar senha</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Informe seu e-mail. Se houver uma conta ativa com esse endereço, enviaremos um link de redefinição.
    </p>

    {{--
        O aviso de "enviamos o link" sai igual exista a conta ou não —
        quem garante isso é RespostaUnicaDoLinkDeSenha, e não a cor
        escolhida aqui. O que sobra para o alerta de erro é o e-mail
        malformado, que é engano de quem digitou e merece o vermelho.
    --}}
    @if (session('status'))
        <x-alerta tipo="sucesso" class="mt-4">{{ session('status') }}</x-alerta>
    @endif

    @error('email')
        <x-alerta tipo="erro" class="mt-4">{{ $message }}</x-alerta>
    @enderror

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <x-campo rotulo="E-mail" para="email" obrigatorio>
            <x-input tipo="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"/>
        </x-campo>

        <x-botao tipo="submit" class="w-full">Enviar link</x-botao>
        <x-botao variante="discreto" href="{{ route('login') }}" class="w-full">Voltar ao login</x-botao>
    </form>
</x-layouts.auth>
