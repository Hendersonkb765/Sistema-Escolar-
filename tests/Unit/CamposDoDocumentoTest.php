<?php

use App\Enums\TipoDeDocumento;
use App\Support\CamposDoDocumento;

it('troca o campo pelo dado', function () {
    $html = CamposDoDocumento::render(
        'Eu, responsável por {{ aluno.nome }}, RA {{ aluno.ra }}, autorizo.',
        ['aluno.nome' => 'Marina Alves', 'aluno.ra' => '20261001'],
    )->toHtml();

    expect($html)->toBe('Eu, responsável por Marina Alves, RA 20261001, autorizo.');
});

it('aceita o campo com e sem espaço dentro das chaves', function () {
    $contexto = ['aluno.nome' => 'Marina'];

    expect(CamposDoDocumento::render('{{aluno.nome}}', $contexto)->toHtml())->toBe('Marina')
        ->and(CamposDoDocumento::render('{{   aluno.nome   }}', $contexto)->toHtml())->toBe('Marina');
});

/*
 * O corpo vem de um formulário. Sem escape, um modelo com `<script>` no
 * meio roda na pré-visualização de quem o recebeu compartilhado.
 */
it('escapa o texto do corpo e o dado que entra nele', function () {
    $html = CamposDoDocumento::render(
        'Aluno <script>alert(1)</script>: {{ aluno.nome }}',
        ['aluno.nome' => '<img src=x onerror=alert(2)>'],
    )->toHtml();

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&lt;img');
});

it('mantém negrito e itálico do enunciado', function () {
    expect(CamposDoDocumento::render('**Atenção**: leia *tudo*.', [])->toHtml())
        ->toBe('<strong>Atenção</strong>: leia <em>tudo</em>.');
});

it('vira quebra de linha o que era quebra de linha', function () {
    expect(CamposDoDocumento::render("Primeira\nSegunda", [])->toHtml())
        ->toBe("Primeira<br>\nSegunda");
});

it('desenha a linha para preencher, com e sem rótulo', function () {
    $comRotulo = CamposDoDocumento::render('{{ linha: Assinatura do responsável }}', [])->toHtml();
    $semRotulo = CamposDoDocumento::render('{{ linha }}', [])->toHtml();

    expect($comRotulo)->toContain('campo-linha')
        ->and($comRotulo)->toContain('Assinatura do responsável')
        ->and($semRotulo)->toContain('campo-linha')
        ->and($semRotulo)->not->toContain('rotulo-do-campo');
});

it('escapa o rótulo do campo de preencher', function () {
    expect(CamposDoDocumento::render('{{ linha: <b>x</b> }}', [])->toHtml())
        ->toContain('&lt;b&gt;')
        ->and(CamposDoDocumento::render('{{ linha: <b>x</b> }}', [])->toHtml())
        ->not->toContain('<b>');
});

/*
 * Um campo de dado vazio não pode sair como espaço em branco: no papel,
 * "Aluno:  " não se distingue de uma linha para preencher, e alguém
 * escreve o nome errado ali.
 */
it('põe um traço onde o dado não existe', function () {
    expect(CamposDoDocumento::render('RA: {{ aluno.ra }}', ['aluno.ra' => null])->toHtml())
        ->toBe('RA: —');
});

it('recusa o campo que não existe', function () {
    expect(CamposDoDocumento::validar('Olá {{ aluno.nomee }}', TipoDeDocumento::Individual))
        ->toBe(['aluno.nomee']);
});

it('recusa o campo do aluno no documento coletivo, e explica', function () {
    expect(CamposDoDocumento::validar('{{ aluno.nome }}', TipoDeDocumento::Coletivo))
        ->toBe(['aluno.nome'])
        ->and(CamposDoDocumento::motivoDaRecusa('aluno.nome', TipoDeDocumento::Coletivo))
        ->toContain('só existe no documento');
});

it('recusa a lista de alunos no documento individual', function () {
    expect(CamposDoDocumento::validar('{{ lista_de_alunos }}', TipoDeDocumento::Individual))
        ->toBe(['lista_de_alunos']);
});

it('aprova o corpo que só usa campo válido', function () {
    $corpo = 'Eu, {{ linha: Responsável }}, autorizo {{ aluno.nome }} '
        .'da turma {{ turma.nome }} em {{ data }}. {{ caixa: Sim }} {{ espaco }}';

    expect(CamposDoDocumento::validar($corpo, TipoDeDocumento::Individual))->toBe([]);
});

it('distingue campo desconhecido de campo do outro tipo', function () {
    expect(CamposDoDocumento::motivoDaRecusa('inventado', TipoDeDocumento::Individual))
        ->toContain('não é um campo do documento');
});

it('lista campos diferentes para cada tipo', function () {
    $individual = CamposDoDocumento::disponiveis(TipoDeDocumento::Individual);
    $coletivo = CamposDoDocumento::disponiveis(TipoDeDocumento::Coletivo);

    expect($individual)->toHaveKey('aluno.nome')
        ->and($individual)->not->toHaveKey('lista_de_alunos')
        ->and($coletivo)->toHaveKey('lista_de_alunos')
        ->and($coletivo)->not->toHaveKey('aluno.nome')
        // Os comuns valem para os dois.
        ->and($individual)->toHaveKey('turma.nome')
        ->and($coletivo)->toHaveKey('turma.nome');
});

/*
 * O caso que o PDF mostrou e nenhum teste pegava: a marca de negrito
 * envolvendo o campo. Formatando antes de trocar, cada `**` cai num
 * pedaço de texto diferente, não encontra o par, e o documento sai com os
 * asteriscos impressos.
 */
it('aplica negrito que envolve o campo', function () {
    $html = CamposDoDocumento::render('o aluno **{{ aluno.nome }}**, RA', ['aluno.nome' => 'Marina'])->toHtml();

    expect($html)->toBe('o aluno <strong>Marina</strong>, RA')
        ->and($html)->not->toContain('*');
});

it('aplica itálico que começa fora e termina dentro do campo', function () {
    $html = CamposDoDocumento::render('*turma {{ turma.nome }}*', ['turma.nome' => '1 A'])->toHtml();

    expect($html)->toBe('<em>turma 1 A</em>');
});

/*
 * O dado trocado não pode virar marcação: um aluno chamado "Ana *Maria*"
 * não deve sair em itálico, e um asterisco solto no nome não pode abrir
 * uma marca que devore o resto do documento.
 */
it('não deixa o dado do aluno virar marcação', function () {
    $html = CamposDoDocumento::render(
        'Aluno: {{ aluno.nome }} fim',
        ['aluno.nome' => 'Ana *Maria* Souza'],
    )->toHtml();

    expect($html)->toBe('Aluno: Ana *Maria* Souza fim');
});

it('não deixa as marcas internas entrarem pelo corpo', function () {
    $html = CamposDoDocumento::render("texto \x01 0 \x02 {{ aluno.nome }}", ['aluno.nome' => 'Marina'])->toHtml();

    expect($html)->toContain('Marina')
        ->and($html)->not->toContain("\x01")
        ->and($html)->not->toContain("\x02");
});

it('desenha a assinatura como linha com rótulo embaixo', function () {
    $html = CamposDoDocumento::render('{{ assinatura: Responsável }}', [])->toHtml();

    expect($html)->toContain('campo-assinatura')
        ->and($html)->toContain('Responsável');
});

/*
 * O mPDF ignora largura de elemento em linha: `inline-block` com
 * `min-width` vira um tracinho de dois milímetros, e o quadradinho vira
 * uma barrinha. A linha precisa de conteúdo de verdade e a caixa, de um
 * caractere.
 */
it('desenha a linha com espaço rígido e a caixa com caractere', function () {
    expect(CamposDoDocumento::render('{{ linha }}', [])->toHtml())
        ->toContain('&nbsp;&nbsp;')
        ->and(CamposDoDocumento::render('{{ caixa }}', [])->toHtml())
        ->toContain('&#9744;');
});

/*
 * `trechos()` alimenta o gerador do .docx, que monta o documento bloco a
 * bloco — o Word não aceita o HTML da folha. Ele passa pelo mesmo caminho
 * de duas etapas do `render()`, e é isso que faz o negrito em volta de um
 * campo valer nos dois formatos.
 */
it('devolve o texto e o campo como trechos', function () {
    expect(CamposDoDocumento::trechos('Aluno: {{ aluno.nome }}.', ['aluno.nome' => 'Marina']))
        ->toBe([
            ['tipo' => 'texto', 'texto' => 'Aluno: ', 'negrito' => false, 'italico' => false],
            ['tipo' => 'texto', 'texto' => 'Marina', 'negrito' => false, 'italico' => false],
            ['tipo' => 'texto', 'texto' => '.', 'negrito' => false, 'italico' => false],
        ]);
});

it('marca em negrito o campo envolvido pelas marcas', function () {
    $trechos = CamposDoDocumento::trechos('**{{ aluno.nome }}**', ['aluno.nome' => 'Marina']);

    expect($trechos)->toBe([
        ['tipo' => 'texto', 'texto' => 'Marina', 'negrito' => true, 'italico' => false],
    ]);
});

it('não dá formatação aos campos de preencher', function () {
    $trechos = CamposDoDocumento::trechos('**{{ linha: nome }}**', []);

    expect($trechos)->toBe([['tipo' => 'linha', 'rotulo' => 'nome']]);
});

it('transforma a quebra de linha em trecho próprio', function () {
    expect(CamposDoDocumento::trechos("um\ndois", []))
        ->toBe([
            ['tipo' => 'texto', 'texto' => 'um', 'negrito' => false, 'italico' => false],
            ['tipo' => 'quebra'],
            ['tipo' => 'texto', 'texto' => 'dois', 'negrito' => false, 'italico' => false],
        ]);
});

it('conta cada campo de bloco uma vez', function () {
    $tipos = array_column(
        CamposDoDocumento::trechos('{{ espaco }}{{ assinatura: X }}{{ caixa }}{{ lista_de_alunos }}', []),
        'tipo',
    );

    expect($tipos)->toBe(['espaco', 'assinatura', 'caixa', 'lista_de_alunos']);
});

/*
 * Os dois caminhos saem do mesmo corpo, então precisam concordar sobre o
 * que está escrito. Se um deles perder um campo, o Word e o PDF passam a
 * entregar documentos diferentes com o mesmo nome.
 */
it('diz a mesma coisa que o HTML sobre o que o corpo contém', function () {
    $corpo = "Eu, {{ linha: responsável }}, autorizo **{{ aluno.nome }}**\n"
        .'da turma {{ turma.nome }}. {{ caixa: Sim }} {{ assinatura: Assinatura }}';

    $contexto = ['aluno.nome' => 'Marina Alves', 'turma.nome' => '1 A'];

    $html = CamposDoDocumento::render($corpo, $contexto)->toHtml();
    $trechos = CamposDoDocumento::trechos($corpo, $contexto);

    $texto = implode('', array_column(
        array_filter($trechos, fn (array $t) => $t['tipo'] === 'texto'),
        'texto',
    ));

    expect($texto)->toContain('Marina Alves')
        ->and($texto)->toContain('1 A')
        ->and($html)->toContain('Marina Alves')
        ->and($html)->toContain('1 A')
        // Os campos de preencher aparecem nos dois, cada um do seu jeito.
        ->and(array_column($trechos, 'tipo'))->toContain('linha', 'caixa', 'assinatura')
        ->and($html)->toContain('campo-linha')
        ->and($html)->toContain('campo-caixa')
        ->and($html)->toContain('campo-assinatura');
});
