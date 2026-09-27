<?php

/*
 * Todo campo de formulário tem borda e espaço interno.
 *
 * O preflight do Tailwind zera a largura da borda de **todo** elemento,
 * e o projeto não usa o plugin `forms`. Sem declarar `border` e
 * `px-3 py-2` no componente, o `<input>` sai sem linha nenhuma e com o
 * texto encostado — foi o que aconteceu, e nenhum teste percebeu.
 *
 * A conferência é no HTML renderizado, e não no arquivo do componente:
 * é o que chega ao navegador que importa.
 */

use Illuminate\Support\Facades\Blade;

/** As classes que um campo precisa carregar. */
const RESPIRO = ['border', 'px-3', 'py-2'];

it('dá borda e respiro aos campos', function (string $componente) {
    $html = Blade::render("<x-{$componente}/>");

    foreach (RESPIRO as $classe) {
        expect($html)->toMatch('/class="[^"]*\b'.preg_quote($classe, '/').'\b[^"]*"/');
    }
})->with(['input', 'select', 'area-texto']);

it('mantém o respiro quando a tela passa classes próprias', function () {
    // `$attributes->class()` mescla; uma classe da tela não pode
    // apagar o padding do componente.
    $html = Blade::render('<x-input class="sm:col-span-2"/>');

    expect($html)->toContain('sm:col-span-2')
        ->toContain('px-3')
        ->toContain('py-2')
        ->toContain('border');
});

it('pega um campo que perdeu o respiro', function () {
    // O teste acima só vale se este padrão realmente falhar no defeito.
    $semRespiro = '<input class="block w-full rounded-lg text-sm">';

    foreach (RESPIRO as $classe) {
        expect($semRespiro)->not->toMatch('/class="[^"]*\b'.preg_quote($classe, '/').'\b[^"]*"/');
    }
});

it('a barra de rolagem segue o layout', function () {
    // Duas sintaxes: `scrollbar-color` é a padronizada e
    // `::-webkit-scrollbar` atende Safari e Chrome antigo. Faltando uma,
    // metade dos navegadores volta à barra cinza do sistema.
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('scrollbar-color')
        ->toContain('::-webkit-scrollbar-thumb')
        ->toContain("theme('colors.slate.700')")
        ->toContain('forced-colors');
});
