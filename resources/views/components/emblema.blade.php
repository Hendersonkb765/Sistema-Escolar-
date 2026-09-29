{{--
    O quadrado com a sigla da escola, ao lado do nome do sistema.

    Vive num componente porque aparece em dois layouts — barra lateral e
    login — e porque a sigla tem regra própria (App\Support\Marca): o
    corte cego dos dois primeiros caracteres deixava um ponto solto
    dentro do quadrado em nomes como "E.E Francisco Pereira".
--}}
<span data-emblema
      {{ $attributes->class('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-marca-600 text-sm font-bold text-white') }}>{{ \App\Support\Marca::sigla() }}</span>
