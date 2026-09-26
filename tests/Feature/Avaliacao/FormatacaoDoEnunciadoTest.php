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

    it('guarda as marcas como texto, sem virar HTML no banco', function () {
        ($this->tela)()
            ->set("formulario.{$this->questao->id}.enunciado", 'Leia o **comando** com *calma*')
            ->call('salvarQuestao', $this->questao->id)
            ->assertHasNoErrors();

        expect($this->questao->refresh()->enunciado)
            ->toBe('Leia o **comando** com *calma*');
    });
});
