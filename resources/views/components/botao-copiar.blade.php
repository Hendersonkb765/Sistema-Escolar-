@props([
    'texto',
    'rotulo' => 'Copiar',
    'confirmacao' => 'Copiado',
    'titulo' => null,
])

{{--
    Copia um texto para a área de transferência.

    `navigator.clipboard` só existe em contexto seguro — https ou
    localhost. Numa escola o sistema roda em http na rede interna, onde
    a API simplesmente não está lá, e um botão que não faz nada ao ser
    clicado é pior que botão nenhum. Por isso o caminho antigo
    (`execCommand`) fica como reserva.

    O retorno é visível: sem ele, quem clica não sabe se funcionou e
    clica de novo.
--}}
<button type="button"
        x-data="{ copiado: false }"
        x-on:click="
            const conteudo = {{ Js::from($texto) }};
            const confirmar = () => { copiado = true; setTimeout(() => copiado = false, 1800); };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(conteudo).then(confirmar);
            } else {
                const campo = document.createElement('textarea');
                campo.value = conteudo;
                campo.setAttribute('readonly', '');
                campo.style.position = 'fixed';
                campo.style.opacity = '0';
                document.body.appendChild(campo);
                campo.select();
                document.execCommand('copy');
                campo.remove();
                confirmar();
            }
        "
        @if ($titulo) title="{{ $titulo }}" @endif
        {{ $attributes->class([
            'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium transition',
            'text-slate-500 hover:bg-slate-100 hover:text-slate-700',
            'dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200',
            'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-marca-500',
        ]) }}>
    <span x-show="! copiado">{{ $rotulo }}</span>
    <span x-show="copiado" x-cloak class="text-emerald-600 dark:text-emerald-400">{{ $confirmacao }}</span>
</button>
