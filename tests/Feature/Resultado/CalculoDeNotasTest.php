<?php

/*
 * Critério de aceite 10: o peso vale **dentro da disciplina**.
 *
 * Uma prova com três disciplinas produz três notas independentes, cada
 * uma de 0 a 10 medida contra a soma dos pesos da sua própria
 * disciplina. Somar questões de disciplinas diferentes numa nota só
 * apagaria exatamente o que a escola quer ver.
 */

use App\Actions\Resultado\CalcularNotasAction;

it('mede cada disciplina contra a soma dos pesos dela', function () {
    // O exemplo do enunciado: pesos 1; 1; 0,5; 0,5; 0,75 somam 3,75.
    // Acertando os dois primeiros e o quarto: 1 + 1 + 0,5 = 2,5.
    expect(CalcularNotasAction::nota(acertos: 2.5, total: 3.75))->toBe(6.67);
});

it('vai de zero a dez', function (float $acertos, float $total, float $esperada) {
    expect(CalcularNotasAction::nota($acertos, $total))->toBe($esperada);
})->with([
    'errou tudo' => [0.0, 3.75, 0.0],
    'acertou tudo' => [3.75, 3.75, 10.0],
    'metade' => [2.0, 4.0, 5.0],
    'arredonda para duas casas' => [1.0, 3.0, 3.33],
]);

it('não divide por zero quando a disciplina não tem peso', function () {
    expect(CalcularNotasAction::nota(0.0, 0.0))->toBe(0.0);
});
