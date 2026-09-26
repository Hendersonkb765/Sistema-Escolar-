<?php

/*
 * Negrito e itálico no enunciado.
 *
 * A formatação é guardada como marca no próprio texto — `**negrito**`,
 * `*itálico*` —, e não como HTML: o enunciado vem do professor, é
 * entrada de usuário, e o Word não lê HTML nenhum. Quem interpreta as
 * marcas é `TextoDoEnunciado`, num lugar só, e os três destinos
 * consultam ele.
 */

use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Turma;
use App\Support\TextoDoEnunciado;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| As marcas
|--------------------------------------------------------------------------
*/

it('converte as marcas em negrito e itálico', function (string $texto, string $esperado) {
    expect((string) TextoDoEnunciado::paraHtml($texto))->toBe($esperado);
})->with([
    'texto limpo' => ['Leia com atenção', 'Leia com atenção'],
    'negrito' => ['Leia o **comando**', 'Leia o <strong>comando</strong>'],
    'itálico' => ['Use *isto* aqui', 'Use <em>isto</em> aqui'],
    'os dois juntos' => ['É ***tudo*** junto', 'É <strong><em>tudo</em></strong> junto'],
    'um de cada' => ['**A** e *B*', '<strong>A</strong> e <em>B</em>'],
    'marca sem fechamento' => ['**não fecha', '**não fecha'],
]);

it('não confunde multiplicação com itálico', function () {
    // Numa prova de lógica isto aparece, e não pode virar formatação.
    expect((string) TextoDoEnunciado::paraHtml('Calcule 3 * 4 * 5'))
        ->toBe('Calcule 3 * 4 * 5');

    expect((string) TextoDoEnunciado::paraHtml('O produto a * b'))
        ->toBe('O produto a * b');
});

it('escapa o que o professor escrever, marcado ou não', function () {
    $html = (string) TextoDoEnunciado::paraHtml('Tag <script>alert(1)</script> e **<b>oi</b>**');

    expect($html)->toContain('&lt;script&gt;')
        ->toContain('<strong>&lt;b&gt;oi&lt;/b&gt;</strong>')
        ->not->toContain('<script>');
});

it('devolve o texto limpo quando não há onde formatar', function () {
    expect(TextoDoEnunciado::semMarcas('Leia o **comando** com *calma*'))
        ->toBe('Leia o comando com calma');
});

it('sabe dizer se há formatação', function () {
    expect(TextoDoEnunciado::temFormatacao('Leia o **comando**'))->toBeTrue()
        ->and(TextoDoEnunciado::temFormatacao('Sem marca nenhuma'))->toBeFalse()
        ->and(TextoDoEnunciado::temFormatacao('Multiplique 2 * 3'))->toBeFalse();
});

it('quebra o texto em trechos com o estilo de cada um', function () {
    expect(TextoDoEnunciado::segmentos('Leia o **comando** com *calma*'))->toBe([
        ['texto' => 'Leia o ', 'negrito' => false, 'italico' => false],
        ['texto' => 'comando', 'negrito' => true, 'italico' => false],
        ['texto' => ' com ', 'negrito' => false, 'italico' => false],
        ['texto' => 'calma', 'negrito' => false, 'italico' => true],
    ]);
});

/*
|--------------------------------------------------------------------------
| Ligar e desligar as marcas
|--------------------------------------------------------------------------
*/

it('soma negrito e itálico na mesma palavra', function () {
    // Era o defeito: alternar cada marca por conta própria fazia o
    // segundo clique desfazer o primeiro, porque `**palavra**` começa e
    // termina com `*`.
    $estado = ['antes' => 'Leia a ', 'selecao' => 'palavra', 'depois' => ' agora'];
    $texto = fn (array $e) => $e['antes'].$e['selecao'].$e['depois'];

    $estado = TextoDoEnunciado::alternar(...[...$estado, 'marca' => TextoDoEnunciado::MARCA_NEGRITO]);
    expect($texto($estado))->toBe('Leia a **palavra** agora');

    $estado = TextoDoEnunciado::alternar(...[...$estado, 'marca' => TextoDoEnunciado::MARCA_ITALICO]);
    expect($texto($estado))->toBe('Leia a ***palavra*** agora')
        ->and((string) TextoDoEnunciado::paraHtml($texto($estado)))
        ->toBe('Leia a <strong><em>palavra</em></strong> agora');
});

it('desliga uma marca sem levar a outra junto', function () {
    $texto = fn (array $e) => $e['antes'].$e['selecao'].$e['depois'];

    $estado = TextoDoEnunciado::alternar('', '***palavra***', '', TextoDoEnunciado::MARCA_NEGRITO);
    expect($texto($estado))->toBe('*palavra*');

    $estado = TextoDoEnunciado::alternar('', '***palavra***', '', TextoDoEnunciado::MARCA_ITALICO);
    expect($texto($estado))->toBe('**palavra**');
});

it('reconhece as marcas encostadas na seleção', function () {
    // Quem marcou a palavra e depois selecionou só a palavra espera
    // desmarcar, não marcar de novo.
    $estado = TextoDoEnunciado::alternar('Leia a **', 'palavra', '** agora', TextoDoEnunciado::MARCA_NEGRITO);

    expect($estado['antes'].$estado['selecao'].$estado['depois'])->toBe('Leia a palavra agora');
});

it('marca o ponto do cursor quando não há seleção', function () {
    $estado = TextoDoEnunciado::alternar('Leia ', '', 'agora', TextoDoEnunciado::MARCA_ITALICO);

    expect($estado['selecao'])->toBe('**')
        ->and($estado['antes'].$estado['selecao'].$estado['depois'])->toBe('Leia **agora');
});

/*
|--------------------------------------------------------------------------
| A tela do professor
|--------------------------------------------------------------------------
*/

describe('na tela do professor', function () {
    beforeEach(function () {
        $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
        $this->paeet = paeet($this->eixo);
        $this->professor = professor($this->eixo);

        $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
            duracaoAnos: 2, autor: $this->paeet);

        $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
            ->create(['periodo' => 1, 'nome' => '1 A']);

        $this->solicitacao = solicitacaoCom($this->paeet, $turma, [
            ['disciplina' => $montagem['disciplinas']['Lógica de Programação'],
                'professor' => $this->professor, 'questoes' => 2],
        ]);

        $this->questao = $this->solicitacao->questoes()->orderBy('ordem')->first();

        $this->tela = fn () => Livewire::actingAs($this->professor)
            ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao->refresh()]);
    });

    it('oferece os botões de negrito e itálico', function () {
        ($this->tela)()
            ->assertSee('Negrito — envolve a seleção em ** **')
            ->assertSee('Itálico — envolve a seleção em * *');
    });

    it('esconde os botões quando a questão está bloqueada', function () {
        app(EncerrarSolicitacaoAction::class)
            ->encerrar($this->solicitacao, $this->paeet);

        ($this->tela)()->assertDontSee('Negrito — envolve a seleção');
    });

    it('mostra a prévia só quando há formatação', function () {
        ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Sem marca nenhuma')
            ->assertDontSee('Prévia');

        ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Leia o **comando**')
            ->assertSee('Prévia')
            ->assertSee('<strong>comando</strong>', escape: false);
    });

    it('aplica a marca pelo servidor e devolve o cursor ao lugar', function () {
        ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Leia a palavra agora')
            ->call('alternarMarca', "formulario.{$this->questao->id}.enunciado",
                'Leia a ', 'palavra', ' agora', TextoDoEnunciado::MARCA_NEGRITO)
            ->assertSet("formulario.{$this->questao->id}.enunciado", 'Leia a **palavra** agora')
            ->assertDispatched('marca-aplicada', campo: "formulario.{$this->questao->id}.enunciado",
                inicio: 7, fim: 18);
    });

    it('ignora caminho que não seja campo de texto da questão', function () {
        // O caminho vem do navegador: nada além dos campos previstos.
        $componente = ($this->tela)();
        $antes = $componente->get('formulario');

        foreach (['formulario.'.$this->questao->id.'.peso', 'solicitacao.titulo', 'formulario'] as $caminho) {
            $componente->call('alternarMarca', $caminho, 'a', 'b', 'c', TextoDoEnunciado::MARCA_NEGRITO);
        }

        expect($componente->get('formulario'))->toBe($antes);
    });

    it('ignora marca que não seja negrito ou itálico', function () {
        $componente = ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Leia a palavra');

        $componente->call('alternarMarca', "formulario.{$this->questao->id}.enunciado",
            'Leia a ', 'palavra', '', '~~');

        $componente->assertSet("formulario.{$this->questao->id}.enunciado", 'Leia a palavra');
    });

    it('guarda as marcas como texto, sem virar HTML no banco', function () {
        ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Leia o **comando** com *calma*')
            ->call('salvarQuestao', $this->questao->id)
            ->assertHasNoErrors();

        expect($this->questao->refresh()->enunciado)
            ->toBe('Leia o **comando** com *calma*');
    });
});
