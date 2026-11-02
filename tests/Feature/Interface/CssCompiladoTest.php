<?php

/*
 * O CSS servido é o compilado em public/build, e é ele que vai para
 * produção junto com o código. O Tailwind só gera a classe que viu na
 * hora do `npm run build`: classe nova numa view, sem build depois, não
 * existe no navegador.
 *
 * Nada quebra em voz alta. A classe que falta some, e a vizinha fica
 * sozinha — foi assim que a linha do curso na escala da semana saiu com
 * `bg-slate-50` (sem o `dark:bg-slate-800/50` que o escurecia) e o texto
 * claro do modo escuro em cima: branco no branco.
 *
 * O teste lê as classes escritas nas views e cobra cada uma do CSS
 * compilado. Falhou? Rode `npm run build` e comite public/build.
 */

/** As classes literais das views: atributos `class` e as strings de `@class`. */
function classesDasViews(): array
{
    $classes = [];

    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($arquivos as $arquivo) {
        if (! str_ends_with($arquivo->getFilename(), '.blade.php')) {
            continue;
        }

        $conteudo = file_get_contents($arquivo->getPathname());

        // class="..." e class('...') sem interpolação; e as chaves de @class([...]).
        preg_match_all('/\bclass="([^"{}$]*)"/', $conteudo, $atributos);
        preg_match_all("/'([^'{}\$]*)'\s*(?:=>|,|\])/", $conteudo, $strings);

        foreach ([...$atributos[1], ...$strings[1]] as $lista) {
            foreach (preg_split('/\s+/', trim($lista)) as $classe) {
                // Só o que tem cara de utilitário do Tailwind com variante ou
                // modificador — é onde mora o esquecimento — e nada de texto.
                // Nome de evento (`livewire:navigated`) tem a mesma cara e não é classe.
                if (preg_match('#^(alpine|livewire|wire|x-on):#', $classe)) {
                    continue;
                }

                if (preg_match('#^!?(?:[a-z-]+:)+[a-z][a-z0-9-]*(?:-[a-z0-9./\[\]%]+)*$#', $classe)
                    || preg_match('#^!?[a-z][a-z0-9-]*-[a-z0-9]+/\d+$#', $classe)
                    || preg_match('#^!?[a-z][a-z0-9-]*-\[[^\]]+\]$#', $classe)) {
                    $classes[$classe][] = str_replace(resource_path('views').'/', '', $arquivo->getPathname());
                }
            }
        }
    }

    return $classes;
}

function seletorDoTailwind(string $classe): string
{
    return '.'.addcslashes($classe, ':/[].%!()');
}

it('tem no CSS compilado toda classe usada nas views', function () {
    $manifesto = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $css = file_get_contents(public_path('build/'.$manifesto['resources/css/app.css']['file']));

    $faltando = collect(classesDasViews())
        ->reject(fn (array $onde, string $classe) => str_contains($css, seletorDoTailwind($classe)))
        ->map(fn (array $onde, string $classe) => $classe.' ('.implode(', ', array_unique($onde)).')');

    expect($faltando->values()->all())->toBe([], 'Classes sem CSS: rode `npm run build`.');
});
