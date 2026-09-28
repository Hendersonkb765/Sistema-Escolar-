<?php

/*
 * As telas da importação e dos resultados, com dados de verdade — duas
 * linhas em cada coleção, como o projeto exige.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\ConferirImportacaoAction;
use App\Actions\Resultado\ConfirmarImportacaoAction;
use App\Enums\Bimestre;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Livewire\Importacoes\ListaImportacoes;
use App\Livewire\Importacoes\NovaImportacao;
use App\Livewire\Resultados\ListaResultados;
use App\Models\Aluno;
use App\Models\Disciplina;
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

/*
 * A tela abria sem prova escolhida e sem mostrar nada — "Escolha uma
 * prova", com um recado dizendo que as notas aparecem depois da
 * importação, que é exatamente o contrário do que estava acontecendo:
 * havia notas, e elas não apareciam.
 *
 * Nenhum teste pegou porque todos faziam `set('prova_id', ...)` antes de
 * olhar, pulando o estado em que a pessoa chega na tela.
 */
it('abre já mostrando a prova mais recente que tem resultado', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertOk()
        ->assertSet('prova_id', (string) $this->prova->id)
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        ->assertDontSee('Escolha uma prova');
});

it('respeita a prova escolhida no endereço', function () {
    ($this->importado)();

    Livewire::withQueryParams(['prova' => (string) $this->segundaProva->id])
        ->actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertSet('prova_id', (string) $this->segundaProva->id);
});

/*
 * Filtrar por um bimestre que não tem a prova escolhida deixava a tela
 * em branco de novo: o select mudava de opções e a escolha ficava
 * apontando para fora da lista.
 */
it('troca de prova quando o bimestre filtrado deixa a escolhida de fora', function () {
    ($this->importado)();

    $componente = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertSet('prova_id', (string) $this->prova->id);

    $outro = $this->prova->bimestre === Bimestre::Primeiro
        ? Bimestre::Segundo
        : Bimestre::Primeiro;

    $componente->set('filtroBimestre', (string) $outro->value)
        ->assertSet('prova_id', '')
        ->assertSee('Nenhuma prova com resultado');
});

it('diz que não há prova com resultado quando de fato não há', function () {
    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertOk()
        ->assertSet('prova_id', '')
        ->assertSee('Nenhuma prova com resultado');
});

/** A tabela de notas, sem o resto da página. */
function tabelaDeNotas(string $html): string
{
    preg_match('/<table data-notas.*?<\/table>/s', $html, $achado);

    return strip_tags($achado[0] ?? '');
}

/**
 * O bloco que identifica a prova, sem o resto da página.
 *
 * Procurar o título com `assertSee` na página inteira não prova nada: ele
 * está escrito no `<option>` do select de provas, e o teste passaria com
 * a tela sem identificação nenhuma. Foi o que aconteceu ao escrever
 * estes testes antes da correção — dois passaram de cara.
 */
function identificacaoDaProva(string $html): string
{
    preg_match('/<section data-identificacao.*?<\/section>/s', $html, $achado);

    return strip_tags($achado[0] ?? '');
}

/*
 * A tabela de notas sozinha não diz de que prova ela é. Quem abre a tela,
 * imprime ou manda um recorte precisa saber o bimestre e a prova sem ter
 * de conferir o que está escolhido no select lá em cima.
 */
it('diz de que prova e de que bimestre são as notas', function () {
    ($this->importado)();

    $this->prova->update(['bimestre' => Bimestre::Terceiro, 'titulo' => 'Avaliação de recuperação']);

    $html = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->assertOk()
        ->html();

    expect(identificacaoDaProva($html))
        ->toContain('Avaliação de recuperação')
        ->toContain('3º bimestre')
        ->toContain('1 A');
});

/*
 * O bimestre vem da coluna, não do título: o título é escrito à mão na
 * montagem e pode dizer qualquer coisa — inclusive o bimestre errado.
 */
it('tira o bimestre da prova, e não do que está escrito no título', function () {
    ($this->importado)();

    $this->prova->update(['bimestre' => Bimestre::Quarto, 'titulo' => 'Prova do 1º bimestre']);

    $html = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->html();

    expect(identificacaoDaProva($html))->toContain('4º bimestre');
});

it('diz quando a prova foi aplicada, se a data estiver preenchida', function () {
    ($this->importado)();

    $this->prova->update(['data_aplicacao' => '2026-05-14']);

    $html = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->html();

    expect(identificacaoDaProva($html))->toContain('14/05/2026');
});

it('não inventa data quando a prova não tem uma', function () {
    ($this->importado)();

    $this->prova->update(['data_aplicacao' => null]);

    $html = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->html();

    expect(identificacaoDaProva($html))->not->toContain('Aplicada em');
});

it('mostra a nota de cada aluno em cada disciplina', function () {
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
        ->assertSee('5,00');
});

/*
 * A tarefa da tela é a nota, e é ela que aparece de saída. O acerto
 * questão a questão continua existindo — é a única leitura nominal por
 * questão do sistema, já que a Análise lê o índice da turma —, mas atrás
 * de um clique.
 */
it('deixa o acerto questão a questão fechado, e abre quando pedem', function () {
    ($this->importado)();

    $componente = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->assertSet('mostrarQuestoes', false)
        ->assertDontSee('✓');

    $componente->set('mostrarQuestoes', true)
        ->assertSee('✓')
        ->assertSee('✗')
        ->assertSee('2/4');
});

/*
 * Com a tabela filtrada por disciplina, o total de acertos precisa contar
 * só o que está à vista. "8/10" ao lado de três colunas é número certo
 * para pergunta nenhuma.
 */
it('conta os acertos só das questões mostradas', function () {
    ($this->importado)();

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->set('mostrarQuestoes', true)
        ->set('disciplinasEscolhidas', [(string) $logica])
        // Lógica tem 2 questões: a Marina acertou as duas, o Caio uma.
        ->assertSee('2/2')
        ->assertSee('1/2')
        ->assertDontSee('/4');
});

/*
|--------------------------------------------------------------------------
| Escolher quais disciplinas aparecem
|--------------------------------------------------------------------------
*/

it('mostra só as disciplinas marcadas', function () {
    ($this->importado)();

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    $html = Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->set('disciplinasEscolhidas', [(string) $logica])
        ->assertOk()
        ->html();

    expect(tabelaDeNotas($html))
        ->toContain('Marina Alves')
        ->toContain('Lógica')
        ->not->toContain('Redes');
});

it('avisa quando ninguém marcou disciplina nenhuma, em vez de abrir vazia', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->set('disciplinasEscolhidas', [])
        ->assertSee('Nenhuma disciplina marcada');
});

it('marca todas de volta com um clique', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('prova_id', (string) $this->prova->id)
        ->set('disciplinasEscolhidas', [])
        ->call('mostrarTodasAsDisciplinas')
        ->assertCount('disciplinasEscolhidas', 2);
});

/*
 * O professor abre vendo só o que ele leciona: procurar as dele entre
 * seis é exatamente o trabalho que esta tela existe para poupar.
 */
it('abre com as disciplinas do professor já marcadas', function () {
    ($this->importado)();

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    $html = Livewire::actingAs($this->profLogica)
        ->test(ListaResultados::class)
        ->assertSet('prova_id', (string) $this->prova->id)
        ->assertSet('disciplinasEscolhidas', [(string) $logica])
        ->html();

    expect(tabelaDeNotas($html))->toContain('Lógica')->not->toContain('Redes');
});

/*
 * "não deve ficar totalmente privado das informações das demais
 * matérias": as outras continuam a um clique.
 */
it('deixa o professor ver as disciplinas dos colegas quando quiser', function () {
    ($this->importado)();

    $html = Livewire::actingAs($this->profLogica)
        ->test(ListaResultados::class)
        ->call('mostrarTodasAsDisciplinas')
        ->html();

    expect(tabelaDeNotas($html))->toContain('Lógica')->toContain('Redes');
});

it('abre com todas as disciplinas para quem é da gestão', function () {
    ($this->importado)();

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertCount('disciplinasEscolhidas', 2);
});

it('volta às disciplinas do professor com um clique', function () {
    ($this->importado)();

    $logica = Disciplina::query()->where('nome', 'Lógica')->value('id');

    Livewire::actingAs($this->profLogica)
        ->test(ListaResultados::class)
        ->call('mostrarTodasAsDisciplinas')
        ->call('mostrarSoAsMinhas')
        ->assertSet('disciplinasEscolhidas', [(string) $logica]);
});

/*
|--------------------------------------------------------------------------
| Filtro por turma
|--------------------------------------------------------------------------
*/

it('filtra as provas pela turma escolhida', function () {
    ($this->importado)();

    $outraTurma = Turma::factory()
        ->doCurso($this->turma->curso)
        ->create(['periodo' => 1, 'nome' => '1 B']);

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->set('filtroTurma', (string) $outraTurma->id)
        // A turma nova não tem prova com resultado: o recorte fica vazio.
        ->assertSet('prova_id', '')
        ->assertSee('Nenhuma prova com resultado');
});

it('oferece só as turmas que já têm prova com resultado', function () {
    ($this->importado)();

    Turma::factory()->doCurso($this->turma->curso)->create(['periodo' => 1, 'nome' => '1 Z']);

    Livewire::actingAs($this->paeet)
        ->test(ListaResultados::class)
        ->assertSee('1 A')
        ->assertDontSee('1 Z');
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
