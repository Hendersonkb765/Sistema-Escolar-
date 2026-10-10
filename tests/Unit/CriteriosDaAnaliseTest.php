<?php

use App\Support\CriteriosDaAnalise;

function criterios(
    float $criterio = 60,
    int $questoes = 2,
    int $respostas = 10,
    float $variacao = 10,
): CriteriosDaAnalise {
    return new CriteriosDaAnalise(
        criterioEsperado: $criterio,
        notaEsperada: 6,
        minimoDeQuestoes: $questoes,
        minimoDeRespostas: $respostas,
        variacaoRelevante: $variacao,
        faixas: [
            ['ate' => 2.0, 'rotulo' => '0 a 2', 'cor' => 'vermelho'],
            ['ate' => 4.0, 'rotulo' => '2 a 4', 'cor' => 'vermelho'],
            ['ate' => 6.0, 'rotulo' => '4 a 6', 'cor' => 'amarelo'],
            ['ate' => 8.0, 'rotulo' => '6 a 8', 'cor' => 'verde'],
            ['ate' => 10.0, 'rotulo' => '8 a 10', 'cor' => 'verde'],
        ],
    );
}

/*
 * "Evidências insuficientes" não é um meio-termo entre bom e ruim: é a
 * recusa de classificar sem base. Por isso vem antes dos outros dois.
 */
it('recusa concluir quando a evidência não basta, mesmo com percentual alto', function () {
    expect(criterios()->situacao(percentual: 100, questoes: 1, respostas: 3))
        ->toBe('inconclusivo');
});

it('recusa concluir quando a evidência não basta, mesmo com percentual baixo', function () {
    expect(criterios()->situacao(percentual: 10, questoes: 1, respostas: 3))
        ->toBe('inconclusivo');
});

it('classifica pelo critério quando há evidência', function () {
    expect(criterios()->situacao(80, questoes: 3, respostas: 30))->toBe('satisfatorio')
        ->and(criterios()->situacao(40, questoes: 3, respostas: 30))->toBe('atencao');
});

it('trata o próprio critério como satisfatório', function () {
    expect(criterios(criterio: 60)->situacao(60, questoes: 3, respostas: 30))->toBe('satisfatorio');
});

it('exige as duas condições de evidência, e não uma delas', function () {
    $c = criterios(questoes: 2, respostas: 10);

    expect($c->temEvidencia(questoes: 5, respostas: 3))->toBeFalse()
        ->and($c->temEvidencia(questoes: 1, respostas: 50))->toBeFalse()
        ->and($c->temEvidencia(questoes: 2, respostas: 10))->toBeTrue();
});

/*
 * O aviso tem de dizer o que falta e contra qual número — "evidência
 * insuficiente" sozinho não diz à coordenação o que fazer.
 */
it('diz o que falta e contra qual mínimo', function () {
    expect(criterios(questoes: 3, respostas: 20)->faltaDeEvidencia(1, 5))
        ->toContain('uma questão só')
        ->toContain('5 respostas')
        ->toContain('20');

    expect(criterios()->faltaDeEvidencia(5, 50))->toBe('');
});

it('segue o critério que a escola configurou, e não 60', function () {
    expect(criterios(criterio: 75)->situacao(70, questoes: 3, respostas: 30))->toBe('atencao')
        ->and(criterios(criterio: 50)->situacao(70, questoes: 3, respostas: 30))->toBe('satisfatorio');
});

/*
 * Dizer "melhorou 1,2 ponto" sobre duas provas diferentes é precisão que
 * o dado não tem.
 */
it('não chama oscilação de mudança', function () {
    $c = criterios(variacao: 10);

    expect($c->variacao(60, 65))->toBeNull()
        ->and($c->variacao(60, 72))->toBe(12.0)
        ->and($c->variacao(72, 60))->toBe(-12.0);
});

it('põe a nota na faixa certa, inclusive nos limites', function () {
    $c = criterios();

    expect($c->faixaDaNota(0))->toBe(0)
        ->and($c->faixaDaNota(2.0))->toBe(0)
        ->and($c->faixaDaNota(2.1))->toBe(1)
        ->and($c->faixaDaNota(6.0))->toBe(2)
        ->and($c->faixaDaNota(10.0))->toBe(4);
});

/*
 * Habilidade e questão não têm a mesma evidência disponível. Numa
 * habilidade, parte dela é quantas questões a mediram. Numa questão esse
 * número é 1 por definição — exigir o mínimo ali deixava toda questão
 * inconclusiva para sempre, e a leitura por questão inteira sem uso.
 */
it('não exige mínimo de questões na leitura de uma questão só', function () {
    $c = criterios(questoes: 2, respostas: 10);

    expect($c->situacao(30, questoes: 1, respostas: 30, agregaQuestoes: true))
        ->toBe('inconclusivo')
        ->and($c->situacao(30, questoes: 1, respostas: 30, agregaQuestoes: false))
        ->toBe('atencao');
});

it('continua exigindo respostas na leitura de uma questão só', function () {
    $c = criterios(respostas: 10);

    expect($c->situacao(30, questoes: 1, respostas: 4, agregaQuestoes: false))
        ->toBe('inconclusivo');
});

it('não reclama de questões quando não as agrega', function () {
    $c = criterios(questoes: 3, respostas: 10);

    expect($c->faltaDeEvidencia(1, 30, agregaQuestoes: false))->toBe('')
        ->and($c->faltaDeEvidencia(1, 30, agregaQuestoes: true))->toContain('uma questão só');
});
