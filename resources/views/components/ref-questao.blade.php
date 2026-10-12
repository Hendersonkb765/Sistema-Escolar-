@props([
    'id',
    'numero',
    /** O título da prova, para o `title`: "questão 2" só identifica dentro de uma. */
    'prova' => null,
    /** true/false quando a referência é de um aluno; null quando é da turma. */
    'acertou' => null,
    /** O aluno, quando se quer abrir a questão com a resposta dele. */
    'aluno' => null,
    'aberta' => false,
    /** Acrescentado ao `title`: disciplina, habilidade, peso. */
    'dica' => null,
])

{{--
    Uma referência clicável a uma questão.

    Toda vez que a tela escreve "questão 7", o próximo pensamento de quem
    lê é "qual era mesmo a 7?". Antes a resposta estava a três cliques e
    uma aba de distância; agora o número é o botão, em qualquer lugar da
    página, e o painel abre por cima sem perder o lugar na tabela.

    `wire:click` no elemento HTML, e não na tag de um componente: dentro
    dela as diretivas não compilam. Aqui é um `<button>` de verdade.
--}}
<button type="button"
        wire:click="{{ $aluno === null ? 'verQuestao('.$id.')' : 'verRespostaDoAluno('.$id.', '.$aluno.')' }}"
        aria-pressed="{{ $aberta ? 'true' : 'false' }}"
        title="{{ $acertou === null ? 'Ver a questão '.$numero : ($acertou ? 'Acertou' : 'Errou').' a questão '.$numero }}{{ $prova ? ' · '.$prova : '' }}{{ $dica ? ' · '.$dica : '' }}"
        {{ $attributes->class([
            'ref-questao',
            'acertou' => $acertou === true,
            'errou' => $acertou === false,
            'aberta' => $aberta,
        ]) }}>
    @if ($acertou !== null)
        {{-- Nunca só por cor: o sinal vai junto. --}}
        <span aria-hidden="true">{{ $acertou ? '✓' : '✗' }}</span>
        <span class="sr-only">{{ $acertou ? 'Acertou' : 'Errou' }}</span>
    @endif
    {{ $slot->isEmpty() ? $numero : $slot }}
</button>
