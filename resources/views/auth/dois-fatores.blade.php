<x-layouts.auth titulo="Verificação em duas etapas">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Verificação em duas etapas</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Informe o código do seu aplicativo autenticador ou um código de recuperação.
    </p>

    @if ($errors->any())
        <x-alerta tipo="erro" class="mt-4">
            <ul class="space-y-0.5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </x-alerta>
    @endif

    <div x-data="{ recuperacao: false }" class="mt-6 space-y-4">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf

            <div x-show="! recuperacao">
                <x-campo rotulo="Código de autenticação" para="code">
                    <x-input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" x-bind:disabled="recuperacao"/>
                </x-campo>
            </div>

            <div x-show="recuperacao" x-cloak>
                <x-campo rotulo="Código de recuperação" para="recovery_code">
                    <x-input id="recovery_code" name="recovery_code" autocomplete="one-time-code" x-bind:disabled="! recuperacao"/>
                </x-campo>
            </div>

            <x-botao tipo="submit" class="w-full">Continuar</x-botao>
        </form>

        <button type="button" @click="recuperacao = ! recuperacao"
                class="w-full text-center text-sm font-medium text-marca-600 hover:text-marca-700 dark:text-marca-400">
            <span x-show="! recuperacao">Usar código de recuperação</span>
            <span x-show="recuperacao" x-cloak>Usar código do aplicativo</span>
        </button>
    </div>
</x-layouts.auth>
