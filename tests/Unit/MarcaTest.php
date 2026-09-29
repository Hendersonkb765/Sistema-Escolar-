<?php

use App\Support\Marca;

it('junta as iniciais das duas primeiras palavras', function () {
    expect(Marca::sigla('PAEET Avaliações'))->toBe('PA')
        ->and(Marca::sigla('Escola Técnica'))->toBe('ET');
});

/*
 * As duas grafias do nome da escola precisam cair na mesma sigla: quem
 * abrevia "Escola Estadual" como "E.E" não espera ver o emblema mudar
 * porque alguém escreveu o nome por extenso no `.env`.
 */
it('dá a mesma sigla às duas grafias do nome da escola', function () {
    expect(Marca::sigla('E.E Francisco Pereira'))->toBe('EE')
        ->and(Marca::sigla('E. E. Prof. Francisco Pereira de Souza Filho'))->toBe('EE');
});

it('não deixa pontuação entrar no emblema', function () {
    expect(Marca::sigla('E.E Francisco Pereira'))->not->toContain('.')
        ->and(Marca::sigla('- Escola -'))->toBe('ES');
});

it('usa as duas primeiras letras quando o nome tem uma palavra só', function () {
    expect(Marca::sigla('Laravel'))->toBe('LA');
});

it('devolve vazio em vez de quebrar quando não há letra nenhuma', function () {
    expect(Marca::sigla('---'))->toBe('')
        ->and(Marca::sigla(''))->toBe('');
});
