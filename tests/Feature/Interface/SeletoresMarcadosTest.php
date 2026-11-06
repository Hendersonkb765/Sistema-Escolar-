<?php

/*
 * Toda opção de um seletor ligado a `wire:model` diz se está escolhida.
 *
 * Sem `@selected`, o HTML sai do servidor com a primeira opção marcada,
 * enquanto o componente diz outra coisa: o filtro lembrado reabre a lista
 * filtrada com o seletor em "Todas", e a edição de uma turma arquivada
 * mostra "Ativa". A página está certa; o controle mente.
 *
 * O teste lê as views, como o de diretivas cruas: quem cria um seletor
 * novo sem a marca é avisado aqui, e não pelo usuário.
 */

it('marca a opção escolhida em todo seletor ligado ao componente', function () {
    $semMarca = [];

    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($arquivos as $arquivo) {
        if (! str_ends_with($arquivo->getFilename(), '.blade.php')) {
            continue;
        }

        $conteudo = file_get_contents($arquivo->getPathname());

        preg_match_all('/<x-select[^\n]*?wire:model(?:\.[a-z0-9.]+)?="([A-Za-z_]+)"[^\n]*\n(.*?)<\/x-select>/s', $conteudo, $seletores, PREG_SET_ORDER);

        foreach ($seletores as [, $propriedade, $corpo]) {
            preg_match_all('/<option.*?<\/option>/s', $corpo, $opcoes);

            foreach ($opcoes[0] as $opcao) {
                if (! str_contains($opcao, '@selected')) {
                    $semMarca[] = str_replace(resource_path('views').'/', '', $arquivo->getPathname())." ({$propriedade})";
                }
            }
        }
    }

    expect(array_values(array_unique($semMarca)))->toBe([], 'Opção sem @selected: o seletor mostra a primeira opção, e não a escolhida.');
});
