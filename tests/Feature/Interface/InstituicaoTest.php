<?php

/*
 * O nome da escola nos documentos é diferente do nome do sistema.
 *
 * `app.name` nomeia o software; `instituicao.nome` nomeia quem o usa.
 * Confundir os dois faz a prova sair assinada pelo programa — foi o que
 * acontecia.
 */

use App\Models\ModeloProva;
use App\Models\Prova;

it('não usa o nome do sistema como nome da escola', function () {
    expect(config('instituicao.nome'))
        ->toBeString()
        ->not->toBe(config('app.name'))
        ->toContain('Francisco Pereira de Souza Filho');
});

it('cai no nome da escola quando nem a prova nem o modelo dizem', function () {
    $prova = new Prova;
    $prova->setRelation('modelo', new ModeloProva);

    expect($prova->instituicaoDaFolha())->toBe(config('instituicao.nome'));
});

it('prefere o modelo ao padrão, e a prova ao modelo', function () {
    $prova = new Prova;
    $prova->setRelation('modelo', new ModeloProva(['instituicao' => 'Escola do Modelo']));

    expect($prova->instituicaoDaFolha())->toBe('Escola do Modelo');

    $prova->instituicao = 'Escola da Prova';

    expect($prova->instituicaoDaFolha())->toBe('Escola da Prova');
});
