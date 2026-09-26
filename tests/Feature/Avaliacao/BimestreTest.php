<?php

/*
 * O bimestre acompanha a solicitação e a prova.
 *
 * São quatro por ano letivo e nenhum a mais — e a prova herda o
 * bimestre das solicitações que produziram as suas questões, para quem
 * monta não ter de lembrar.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\Bimestre;
use App\Livewire\Provas\MontarProva;
use App\Livewire\Solicitacoes\FormularioSolicitacao;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);
    $this->montagem = $montagem;

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'criado_por' => $this->paeet->id,
    ]);

    /** Abre e aprova uma solicitação inteira, no bimestre pedido. */
    $this->aprovada = function (Bimestre $bimestre, array $pares) {
        $solicitacao = solicitacaoCom($this->paeet, $this->turma, $pares,
            titulo: 'Avaliação '.$bimestre->value, bimestre: $bimestre);

        foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
            enviarParte($parte, $pares[$indice]['professor']);
        }

        $analisar = app(AnalisarQuestaoAction::class);

        foreach ($solicitacao->questoes()->get() as $questao) {
            $analisar->aprovar($questao, $this->paeet);
        }

        return $solicitacao;
    };
});

it('grava o bimestre escolhido na solicitação', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica],
    ], bimestre: Bimestre::Terceiro);

    expect($solicitacao->bimestre)->toBe(Bimestre::Terceiro)
        ->and($solicitacao->bimestre->rotulo())->toBe('3º bimestre');
});

it('tem quatro bimestres e nenhum a mais', function () {
    expect(Bimestre::valores())->toBe([1, 2, 3, 4])
        ->and(Bimestre::tryFrom(5))->toBeNull()
        ->and(Bimestre::tryFrom(0))->toBeNull();
});

it('recusa bimestre fora do intervalo na tela da solicitação', function () {
    $componente = Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->set('turma_id', $this->turma->id)
        ->set('bimestre', 5);

    $componente
        ->set('partes.0.disciplina_id', (string) $this->montagem['disciplinas']['Lógica']->id)
        ->set('partes.0.professor_id', (string) $this->profLogica->id)
        ->set('partes.0.quantidade_questoes', '2')
        ->call('salvar')
        ->assertHasErrors('bimestre');

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('oferece os quatro bimestres na tela da solicitação', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->assertSee('1º bimestre')
        ->assertSee('2º bimestre')
        ->assertSee('3º bimestre')
        ->assertSee('4º bimestre');
});

/*
|--------------------------------------------------------------------------
| A prova herda o bimestre das questões
|--------------------------------------------------------------------------
*/

it('monta a prova no bimestre das solicitações de origem', function () {
    ($this->aprovada)(Bimestre::Terceiro, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);

    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    expect($prova->bimestre)->toBe(Bimestre::Terceiro);
});

it('cai no primeiro quando as questões vêm de bimestres diferentes', function () {
    ($this->aprovada)(Bimestre::Segundo, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);
    ($this->aprovada)(Bimestre::Quarto, [
        ['disciplina' => $this->montagem['disciplinas']['Redes'], 'professor' => $this->profRedes, 'questoes' => 2],
    ]);

    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    // Não há o que herdar: quem monta escolhe na tela.
    expect($prova->bimestre)->toBe(Bimestre::Primeiro);
});

it('respeita o bimestre escolhido na montagem, acima do herdado', function () {
    ($this->aprovada)(Bimestre::Segundo, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);

    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral', bimestre: Bimestre::Quarto,
    );

    expect($prova->bimestre)->toBe(Bimestre::Quarto);
});

it('sugere o bimestre herdado na tela de montagem', function () {
    ($this->aprovada)(Bimestre::Terceiro, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->assertSet('bimestre', 3)
        ->assertSee('3º bimestre');
});

it('recusa bimestre fora do intervalo na montagem', function () {
    ($this->aprovada)(Bimestre::Primeiro, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->set('modelo_prova_id', $this->modelo->id)
        ->set('titulo', 'Avaliação bimestral')
        ->set('bimestre', 9)
        ->call('montar')
        ->assertHasErrors('bimestre');

    expect(Prova::query()->count())->toBe(0);
});

it('imprime o bimestre no cabeçalho da folha e no Word', function () {
    ($this->aprovada)(Bimestre::Segundo, [
        ['disciplina' => $this->montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
    ]);

    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    expect(app(RenderizarProvaAction::class)->paraTela($prova))
        ->toContain('2º bimestre');

    $docx = app(GerarDocxDaProvaAction::class)->conteudo($prova, $this->paeet);

    $arquivo = tempnam(sys_get_temp_dir(), 'prova').'.docx';
    file_put_contents($arquivo, $docx);

    $zip = new ZipArchive;
    $zip->open($arquivo);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($arquivo);

    expect($xml)->toContain('2º bimestre');
});
