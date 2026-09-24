<?php

/*
 * O guarda de lazy loading tem de morder.
 *
 * `proibirLazyLoading()` depende de um detalhe do framework: a
 * propriedade `preventsLazyLoading` de cada model hidratado. Se uma
 * atualização do Laravel mudar isso, o guarda para de funcionar em
 * silêncio — e as centenas de testes de tela passam à toa, deixando
 * passar exatamente o 500 que eles existem para pegar.
 *
 * Este teste falha nesse dia.
 */

use App\Models\Curso;
use App\Models\Eixo;
use Illuminate\Database\LazyLoadingViolationException;

it('estoura ao acessar relação fora do eager load, com um registro só', function () {
    // Um registro só: é o caso que o `Model::preventLazyLoading()` do
    // framework não cobre sozinho, porque o `Builder::hydrate` só marca
    // os models quando a consulta devolve mais de um.
    Curso::factory()->noEixo(Eixo::factory()->create())->create();

    expect(Curso::query()->count())->toBe(1);

    $curso = Curso::query()->first();

    expect(fn () => $curso->eixo->nome)->toThrow(LazyLoadingViolationException::class);
});

it('estoura também quando a consulta devolve vários registros', function () {
    $eixo = Eixo::factory()->create();

    Curso::factory()->noEixo($eixo)->count(2)->create();

    $cursos = Curso::query()->get();

    expect($cursos)->toHaveCount(2)
        ->and(fn () => $cursos->first()->eixo->nome)->toThrow(LazyLoadingViolationException::class);
});

it('não reclama do que entrou no eager load', function () {
    $eixo = Eixo::factory()->create(['nome' => 'Tecnologia']);

    Curso::factory()->noEixo($eixo)->count(2)->create();

    $cursos = Curso::query()->with('eixo')->get();

    expect($cursos->first()->eixo->nome)->toBe('Tecnologia');
});
