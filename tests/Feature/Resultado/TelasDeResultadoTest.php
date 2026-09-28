<?php

/*
 * As telas da importação e dos resultados, com dados de verdade — duas
 * linhas em cada coleção, como o projeto exige.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\ConferirImportacaoAction;
use App\Actions\Resultado\ConfirmarImportacaoAction;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Livewire\Importacoes\ListaImportacoes;
use App\Livewire\Importacoes\NovaImportacao;
use App\Livewire\Resultados\ListaResultados;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves', 'ra' => '1001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '1002']);

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->profRedes, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    $professores = [$this->profLogica, $this->profRedes];

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
        enviarParte($parte, $professores[$indice]);
    }

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    // Duas provas: o select da tela nunca fica com uma linha só.
    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Avaliação bimestral',
    );
    $this->prova->update(['status' => StatusProva::Aplicada]);

    $this->segundaProva = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Segunda chamada',
    );
    $this->segundaProva->update(['status' => StatusProva::Aplicada]);

    $this->arquivo = function (array $linhas): UploadedFile {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();

        $aba->fromArray([
            'Quiz Name', 'Class', 'ZipGrade Id', 'External Id', 'First Name', 'Last Name',
            'Num Questions', 'Num Correct', 'Percent Correct', 'Key Version', 'Q1', 'Q2', 'Q3', 'Q4',
        ], null, 'A1');
        $aba->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'notas').'.xlsx';
        (new Xlsx($planilha))->save($caminho);

        $conteudo = (string) file_get_contents($caminho);
        @unlink($caminho);

        // `createWithContent` devolve um `Testing\File`, que é o que o
        // `set()` do Livewire sabe enviar.
        return UploadedFile::fake()->createWithContent('resultados.xlsx', $conteudo);
    };

    $this->duasLinhas = [
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Alves', 4, 4, 100, 'A', 1, 1, 1, 1],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
    ];

    /** Importa de ponta a ponta, para as telas de resultado. */
    $this->importado = function () {
        $importacao = Importacao::create([
            'prova_id' => $this->prova->id,
            'usuario_id' => $this->paeet->id,
            'arquivo' => ($this->arquivo)($this->duasLinhas)->store('importacoes', 'local'),
            'nome_original' => 'resultados.xlsx',
            'hash' => hash('sha256', 'x'),
            'status' => StatusImportacao::Validando,
        ]);

        app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
        app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

        return $importacao->refresh();
    };
});

/*
|--------------------------------------------------------------------------
| A tela de importação
|--------------------------------------------------------------------------
*/

it('lista as provas que aceitam importação', function () {
    Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->assertOk()
        ->assertSee('Avaliação bimestral')
        ->assertSee('Segunda chamada')
        ->assertSee('O cadastro dos alunos não é alterado');
});

it('confere a planilha sem gravar nada e mostra o relatório', function () {
    $componente = Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->set('prova_id', $this->prova->id)
        ->set('planilha', ($this->arquivo)($this->duasLinhas))
        ->call('conferir')
        ->assertHasNoErrors();

    $componente
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        ->assertSee('Reconhecida')
        ->assertSee('Confirmar 2 resultado(s)');

    expect(ResultadoAluno::query()->count())->toBe(0)
        ->and(Importacao::query()->sole()->status)->toBe(StatusImportacao::Validada);
});

it('grava só no segundo passo e leva para as notas', function () {
    $componente = Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->set('prova_id', $this->prova->id)
        ->set('planilha', ($this->arquivo)($this->duasLinhas))
        ->call('conferir');

    $componente->call('confirmar')
        ->assertRedirect(route('resultados.index', ['prova' => $this->prova->id]));

    expect(ResultadoAluno::query()->count())->toBe(2)
        ->and(Importacao::query()->sole()->status)->toBe(StatusImportacao::Confirmada);
});

it('mostra na tela por que a linha foi recusada', function () {
    Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->set('prova_id', $this->prova->id)
        ->set('planilha', ($this->arquivo)([
            ['Prova', '1 A', '5001', '1001', 'Marina', 'Alves', 4, 4, 100, 'A', 1, 1, 1, 1],
            ['Prova', '1 A', '9999', '', 'Fulano', 'Inexistente', 4, 0, 0, 'A', 0, 0, 0, 0],
        ]))
        ->call('conferir')
        ->assertSee('Recusada')
        ->assertSee('Nenhum aluno desta turma corresponde a este nome.')
        ->assertSee('Confirmar 1 resultado(s)');
});

it('descarta a conferência sem deixar rastro de resultado', function () {
    Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->set('prova_id', $this->prova->id)
        ->set('planilha', ($this->arquivo)($this->duasLinhas))
        ->call('conferir')
        ->call('descartar')
        ->assertDispatched('notificar');

    expect(ResultadoAluno::query()->count())->toBe(0)
        ->and(Importacao::query()->sole()->status)->toBe(StatusImportacao::Cancelada);
});

it('exige a prova e o arquivo', function () {
    Livewire::actingAs($this->paeet)
        ->test(NovaImportacao::class)
        ->call('conferir')
        ->assertHasErrors(['prova_id', 'planilha']);
});

it('lista as importações com duas linhas', function () {
    ($this->importado)();

    Importacao::create([
        'prova_id' => $this->segundaProva->id,
        'usuario_id' => $this->paeet->id,
        'arquivo' => 'importacoes/outra.xlsx',
        'nome_original' => 'segunda-chamada.xlsx',
        'hash' => hash('sha256', 'y'),
        'status' => StatusImportacao::Validada,
    ]);

    Livewire::actingAs($this->paeet)
        ->test(ListaImportacoes::class)
        ->assertOk()
        ->assertSee('resultados.xlsx')
        ->assertSee('segunda-chamada.xlsx')
        ->assertSee('Avaliação bimestral')
        ->assertSee('Segunda chamada');
});

/*
|--------------------------------------------------------------------------
| A tela de resultados
|--------------------------------------------------------------------------
*/

it('mostra a nota de cada disciplina e o acerto de cada questão', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->assertOk()
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        // Uma nota por disciplina.
        ->assertSee('Lógica')
        ->assertSee('Redes')
        ->assertSee('10,00')
        ->assertSee('5,00')
        // Acerto e erro questão a questão.
        ->assertSee('✓')
        ->assertSee('✗')
        ->assertSee('2/4');
});

it('filtra as questões por disciplina', function () {
    ($this->importado)();

    $logica = $this->prova->questoes()
        ->whereHas('disciplina', fn ($q) => $q->where('nome', 'Lógica'))
        ->pluck('disciplina_id')->first();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->set('filtroDisciplina', (string) $logica)
        ->assertOk()
        ->assertSee('Marina Alves');
});

it('avisa quando a prova ainda não tem resultado', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->segundaProva->id)
        ->assertSee('Esta prova ainda não tem resultados');
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega ao professor as telas de importação', function () {
    Livewire::actingAs($this->profLogica)->test(NovaImportacao::class)->assertForbidden();
    Livewire::actingAs($this->profLogica)->test(ListaImportacoes::class)->assertForbidden();
});

it('mostra ao professor só os resultados das provas com questão dele', function () {
    ($this->importado)();

    // O professor de Lógica tem questões nesta prova.
    Livewire::actingAs($this->profLogica)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->assertOk()
        ->assertSee('Marina Alves');

    $estranho = professor($this->eixo);

    Livewire::actingAs($estranho)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->assertOk()
        ->assertDontSee('Marina Alves');
});

it('não vaza importação de outro Eixo na listagem', function () {
    ($this->importado)();

    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(ListaImportacoes::class)
        ->assertOk()
        ->assertDontSee('resultados.xlsx');
});
