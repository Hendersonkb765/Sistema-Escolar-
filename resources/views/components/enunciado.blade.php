@props([
    'questao',
    'texto' => null,
])

{{-- Enunciado completo: o comando da questão seguido dos blocos de apoio
     na ordem em que o professor os montou. --}}
<div class="space-y-3">
    @if ($texto ?? $questao->enunciado)
        <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $texto ?? $questao->enunciado }}</p>
    @endif

    @foreach ($questao->blocos as $bloco)
        @if ($bloco->ehCodigo())
            <x-bloco-codigo :linguagem="$bloco->linguagem?->value ?? 'plaintext'"
                            :rotulo="$bloco->linguagem?->rotulo()">{{ $bloco->conteudo }}</x-bloco-codigo>
        @elseif ($bloco->ehImagem())
            @if ($bloco->urlDaImagem())
                <figure>
                    <img src="{{ $bloco->urlDaImagem() }}" alt="{{ $bloco->legenda ?: 'Imagem do enunciado' }}"
                         class="max-h-96 rounded-lg border border-slate-200 dark:border-slate-800">
                    @if ($bloco->legenda)
                        <figcaption class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $bloco->legenda }}</figcaption>
                    @endif
                </figure>
            @endif
        @else
            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $bloco->conteudo }}</p>
        @endif
    @endforeach
</div>
