<?php

/*
 * O nome da escola nos documentos é diferente do nome do sistema.
 *
 * `app.name` nomeia o software; `instituicao.nome` nomeia quem o usa.
 * Confundir os dois faz a prova sair assinada pelo programa — foi o que
 * acontecia.
 */

use App\Models\Eixo;
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

/*
 * O nome do sistema aparece na barra lateral, no login e no título da
 * aba. Trocá-lo no `.env` tem de bastar: qualquer lugar que ainda traga
 * o nome antigo cravado no Blade continuaria certo na tela de quem não
 * trocou e errado na de quem trocou.
 */
/**
 * Lê o que está escrito dentro do quadradinho da marca.
 *
 * Procurar a sigla com `assertSee` não serve: duas letras aparecem em
 * qualquer página, e o teste passa mesmo com o emblema errado.
 */
function siglaDoEmblema(string $html): string
{
    preg_match('/<span data-emblema[^>]*>\s*([^<\s]*)\s*</', $html, $encontrado);

    return $encontrado[1] ?? '';
}

it('mostra na barra lateral o nome configurado', function () {
    config()->set('app.name', 'Escola Teste');

    $resposta = $this->actingAs(paeetAdmin(Eixo::factory()->create()))
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Escola Teste');

    expect(siglaDoEmblema($resposta->getContent()))->toBe('ET');
});

it('mostra no login o nome configurado', function () {
    config()->set('app.name', 'Escola Teste');

    $resposta = $this->get(route('login'))
        ->assertOk()
        ->assertSee('Escola Teste');

    expect(siglaDoEmblema($resposta->getContent()))->toBe('ET');
});

/*
 * O nome novo da escola começa por sigla pontuada. Cortar os dois
 * primeiros caracteres devolvia "E.", com um ponto solto dentro do
 * quadrado.
 */
it('não deixa pontuação no emblema quando o nome começa por sigla', function () {
    config()->set('app.name', 'E.E Francisco Pereira');

    $resposta = $this->get(route('login'))->assertOk();

    expect(siglaDoEmblema($resposta->getContent()))->toBe('EE');
});

/*
 * A lista de modelos mostra, embaixo do nome do modelo, de quem é a
 * folha. Quando o modelo não diz nada, o padrão é a escola — não o
 * sistema, que era o que estava escrito ali.
 */
it('usa o nome da escola como padrão na lista de modelos', function () {
    $eixo = Eixo::factory()->create();
    config()->set('app.name', 'Nome Do Sistema');
    config()->set('instituicao.nome', 'Escola Padrão Da Configuração');

    ModeloProva::factory()->for($eixo)->create(['nome' => 'Modelo A', 'instituicao' => null]);
    ModeloProva::factory()->for($eixo)->create(['nome' => 'Modelo B', 'instituicao' => null]);

    $this->actingAs(paeetAdmin($eixo))
        ->get(route('modelos-prova.index'))
        ->assertOk()
        ->assertSee('Escola Padrão Da Configuração');
});
