<?php

/*
 * O professor pode preencher as questões pedidas numa planilha: baixa o
 * modelo da disciplina — numerado, com as alternativas que a solicitação
 * pede —, preenche e envia.
 *
 * Em dois passos, e tudo ou nada: a conferência aponta cada linha que
 * precisa de correção, e a importação só acontece com a planilha inteira
 * certa. As questões entram como rascunho; entregar continua sendo o
 * botão de enviar da tela.
 */

use App\Enums\StatusQuestao;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use App\Support\PlanilhaDeQuestoes;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->colega = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], autor: $this->paeet);
    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create(['periodo' => 1, 'nome' => '1 A']);

    $this->solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->professor, 'questoes' => 3],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->colega, 'questoes' => 2],
    ], alternativas: 4, titulo: 'Avaliação bimestral');

    $this->parte = $this->solicitacao->partes()->where('professor_id', $this->professor->id)->sole();
    $this->parteDoColega = $this->solicitacao->partes()->where('professor_id', $this->colega->id)->sole();

    /** A planilha do modelo desta parte, com estas linhas a partir da segunda. */
    $this->xlsx = function (array $linhas) {
        $planilha = PlanilhaDeQuestoes::modelo($this->parte->load('solicitacao', 'disciplina'));
        $planilha->getSheet(0)->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'questoes').'.xlsx';
        (new Xlsx($planilha))->save($caminho);

        return UploadedFile::fake()->createWithContent('questoes.xlsx', file_get_contents($caminho));
    };

    $this->linha = fn (int $n, array $campos = []) => array_values(array_merge([
        'questao' => $n,
        'enunciado' => "Qual é o resultado da questão {$n}?",
        'habilidade' => 'Aplicar condicionais',
        'pontuacao' => '1',
        'a' => 'Alternativa um', 'b' => 'Alternativa dois', 'c' => 'Alternativa três', 'd' => 'Alternativa quatro',
        'correta' => 'B',
    ], $campos));

    $this->tela = fn ($quem = null) => Livewire::actingAs($quem ?? $this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao]);

    $this->conferir = fn ($arquivo) => ($this->tela)()
        ->call('abrirImportacao', $this->parte->id)
        ->set('planilhaDeQuestoes', $arquivo)
        ->call('conferirPlanilha');
});

// --- Os modelos ----------------------------------------------------------

it('baixa o modelo XLSX da disciplina, numerado e com uma aba de instruções', function () {
    $resposta = $this->actingAs($this->professor)->get(route('solicitacoes.partes.modelo', $this->parte));

    $resposta->assertOk()->assertDownload('questoes-logica-1-a.xlsx');

    $caminho = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
    file_put_contents($caminho, $resposta->streamedContent());
    $planilha = IOFactory::load($caminho);
    $aba = $planilha->getSheet(0);

    expect($aba->rangeToArray('A1:I1')[0])->toBe([
        'Questão', 'Enunciado', 'Habilidade', 'Pontuação',
        'Alternativa A', 'Alternativa B', 'Alternativa C', 'Alternativa D', 'Correta',
    ])
        ->and(array_map('intval', array_column($aba->rangeToArray('A2:A4'), 0)))->toBe([1, 2, 3])
        ->and($aba->getHighestRow())->toBe(4)
        ->and($planilha->getSheet(1)->getTitle())->toBe('Como preencher')
        ->and($planilha->getSheet(1)->getCell('A1')->getValue())->toBe('Lógica — 3 questão(ões), 4 alternativas cada');
});

it('baixa o modelo CSV para o Excel brasileiro: ponto e vírgula e BOM', function () {
    $resposta = $this->actingAs($this->professor)->get(route('solicitacoes.partes.modelo', ['parte' => $this->parte, 'formato' => 'csv']));

    $resposta->assertOk()->assertDownload('questoes-logica-1-a.csv');

    $linhas = preg_split('/\r?\n/', trim(substr($resposta->streamedContent(), 3)));

    expect(substr($resposta->streamedContent(), 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(str_getcsv($linhas[0], ';'))->toBe([
            'Questão', 'Enunciado', 'Habilidade', 'Pontuação',
            'Alternativa A', 'Alternativa B', 'Alternativa C', 'Alternativa D', 'Correta',
        ])
        ->and(count($linhas))->toBe(4)
        ->and(str_getcsv($linhas[3], ';')[0])->toBe('3');
});

it('põe no modelo as alternativas que a solicitação pede', function () {
    $this->solicitacao->update(['quantidade_alternativas' => 5]);

    $cabecalho = PlanilhaDeQuestoes::modelo($this->parte->fresh()->load('solicitacao', 'disciplina'))->getSheet(0)->rangeToArray('A1:J1')[0];

    expect($cabecalho[8])->toBe('Alternativa E')
        ->and($cabecalho[9])->toBe('Correta');
});

it('não entrega o modelo de uma parte a quem não a responde', function () {
    $this->actingAs($this->colega)->get(route('solicitacoes.partes.modelo', $this->parte))->assertForbidden();
    $this->actingAs($this->paeet)->get(route('solicitacoes.partes.modelo', $this->parte))->assertForbidden();
});

// --- Importar ------------------------------------------------------------

it('confere sem gravar, e importa as questões como rascunho completo', function () {
    $tela = ($this->conferir)(($this->xlsx)([
        ($this->linha)(1, ['pontuacao' => '1,5', 'correta' => 'b']),
        ($this->linha)(2, ['correta' => 'D']),
        ($this->linha)(3, ['pontuacao' => '2 pontos']),
    ]))
        ->assertSee('Planilha sem erros')
        ->assertSee('3 questão(ões) prontas para entrar como rascunho.');

    expect(Questao::query()->whereNotNull('enunciado')->count())->toBe(0);

    $tela->call('importarPlanilha')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso'
            && str_starts_with($dados['mensagem'], '3 questão(ões) de Lógica preenchida(s) pela planilha, como rascunho.'))
        ->assertSet('importandoParte', null);

    $primeira = $this->parte->questoes()->where('ordem', 1)->with('alternativas')->sole();

    expect($primeira->enunciado)->toBe('Qual é o resultado da questão 1?')
        ->and($primeira->habilidade)->toBe('Aplicar condicionais')
        ->and((float) $primeira->peso)->toBe(1.5)
        ->and($primeira->alternativaCorreta()->letra)->toBe('B')
        ->and($primeira->alternativas->sortBy('letra')->pluck('texto')->all())
        ->toBe(['Alternativa um', 'Alternativa dois', 'Alternativa três', 'Alternativa quatro'])
        ->and($primeira->status)->toBe(StatusQuestao::Rascunho)
        ->and($primeira->estaCompleta())->toBeTrue()
        ->and($this->parte->questoes()->where('ordem', 2)->with('alternativas')->sole()->alternativaCorreta()->letra)->toBe('D')
        ->and((float) $this->parte->questoes()->where('ordem', 3)->sole()->peso)->toBe(2.0);

    // A tela passa a mostrar o que veio da planilha.
    $tela->assertSet("formulario.{$primeira->id}.enunciado", 'Qual é o resultado da questão 1?');
});

it('importa e deixa o envio com o professor, que entrega pela tela', function () {
    $tela = ($this->conferir)(($this->xlsx)([($this->linha)(1), ($this->linha)(2), ($this->linha)(3)]))
        ->call('importarPlanilha');

    expect($this->parte->fresh()->enviada_em)->toBeNull();

    $tela->call('enviarParte', $this->parte->id)
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso');

    expect($this->parte->fresh()->enviada_em)->not->toBeNull();
});

it('aponta cada linha que precisa de correção, e não importa nada', function () {
    $tela = ($this->conferir)(($this->xlsx)([
        ($this->linha)(1, ['enunciado' => '', 'pontuacao' => 'abc']),
        ($this->linha)(2, ['c' => '', 'correta' => 'F']),
        ($this->linha)(7),
        ($this->linha)(2, ['habilidade' => '', 'correta' => '']),
    ]));

    $erros = collect($tela->get('relatorioDeQuestoes')['linhas'])->pluck('erros', 'linha')->all();

    expect($erros)->toBe([
        2 => ['sem enunciado', 'pontuação "abc" inválida: use um número maior que zero, como 1 ou 1,5'],
        3 => ['alternativa C vazia', 'alternativa correta "F" não existe: use A a D'],
        4 => ['a questão 7 não existe: esta disciplina tem 3 questão(ões), de 1 a 3'],
        5 => ['sem habilidade', 'sem a alternativa correta: informe a letra (A a D)', 'a questão 2 já aparece na linha 3'],
    ]);

    $html = $tela->assertSee('4 linha(s) precisam de correção')
        ->assertSeeHtml('Sem enunciado; pontuação &quot;abc&quot; inválida')
        ->html();

    // O botão fica travado, e diz por quê.
    preg_match('/<button[^>]*wire:click="importarPlanilha"[^>]*>/', $html, $botao);

    expect($botao[0])->toMatch('/\sdisabled[\s>]/')
        ->and($html)->toContain('title="Corrija as 4 linha(s) com erro antes de importar."')
        ->and(substr_count($html, 'data-linha-com-erro'))->toBe(4);

    $tela->call('importarPlanilha')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'A planilha tem 4 linha(s) com erro. Corrija as linhas indicadas e confira de novo.');

    expect(Questao::query()->whereNotNull('enunciado')->count())->toBe(0);
});

it('importa só as linhas preenchidas, e diz quais ficaram como estão', function () {
    $tela = ($this->conferir)(($this->xlsx)([($this->linha)(1), [2], [3]]))
        ->assertSee('Sem preenchimento na planilha: questão(ões) 2, 3. Continuam como estão.')
        ->call('importarPlanilha');

    expect($this->parte->questoes()->whereNotNull('enunciado')->pluck('ordem')->all())->toBe([1]);
});

it('avisa que substitui o rascunho que já existe, e leva junto os blocos dele', function () {
    $questao = $this->parte->questoes()->where('ordem', 1)->sole();
    completarQuestao($questao, $this->professor, enunciado: 'Rascunho antigo');
    $questao->blocos()->create(['tipo' => 'codigo', 'ordem' => 1, 'conteudo' => 'print(1)', 'linguagem' => 'python']);

    $tela = ($this->conferir)(($this->xlsx)([($this->linha)(1), ($this->linha)(2)]))
        ->assertSee('O rascunho atual da(s) questão(ões) 1 será substituído.')
        ->assertSee('Substitui o rascunho atual');

    $tela->call('importarPlanilha');

    expect($questao->fresh()->enunciado)->toBe('Qual é o resultado da questão 1?')
        ->and($questao->blocos()->count())->toBe(0);
});

it('lê o CSV do Excel brasileiro, com outros nomes de coluna e vírgula decimal', function () {
    $conteudo = mb_convert_encoding(
        "N;Enunciado;Habilidade avaliada;Peso;Alt. A;Alt. B;Alt. C;Alt. D;Gabarito\n"
        ."1;Quanto é 2 + 2?;Somar;0,5;3;4;5;6;b\n",
        'Windows-1252', 'UTF-8',
    );

    ($this->conferir)(UploadedFile::fake()->createWithContent('questoes.csv', $conteudo))
        ->assertSee('Planilha sem erros')
        ->call('importarPlanilha');

    $questao = $this->parte->questoes()->where('ordem', 1)->with('alternativas')->sole();

    expect($questao->enunciado)->toBe('Quanto é 2 + 2?')
        ->and((float) $questao->peso)->toBe(0.5)
        ->and($questao->alternativaCorreta()->texto)->toBe('4');
});

it('recusa a planilha sem as colunas, ou com alternativas a mais, dizendo o quê', function (string $cabecalho, string $mensagem) {
    ($this->conferir)(UploadedFile::fake()->createWithContent('questoes.csv', "{$cabecalho}\n1;x;x;1;a;b;c;d;e;A\n"))
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'erro' && $dados['mensagem'] === $mensagem)
        ->assertSet('relatorioDeQuestoes', null);
})->with([
    'sem pontuação e sem a alternativa D' => [
        'Questão;Enunciado;Habilidade;Alternativa A;Alternativa B;Alternativa C;Correta',
        'A planilha não tem a(s) coluna(s): Pontuação, Alternativa D. Baixe o modelo desta disciplina e preencha sem mudar o cabeçalho.',
    ],
    'com a alternativa E' => [
        'Questão;Enunciado;Habilidade;Pontuação;Alternativa A;Alternativa B;Alternativa C;Alternativa D;Alternativa E;Correta',
        'Esta solicitação pede 4 alternativas (A a D), e a planilha tem coluna(s) para Alternativa E. Baixe o modelo desta disciplina.',
    ],
]);

it('não importa sobre questões já enviadas', function () {
    enviarParte($this->parte, $this->professor);

    // Na tela, o botão some com a parte entregue.
    ($this->tela)()->assertDontSee('Importar planilha');

    ($this->tela)()
        ->call('abrirImportacao', $this->parte->id)
        ->set('planilhaDeQuestoes', ($this->xlsx)([($this->linha)(1)]))
        ->call('conferirPlanilha')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['mensagem']
            === 'As questões de Lógica já foram enviadas. A planilha só preenche questões ainda em rascunho.');
});

it('não deixa importar na parte de outro professor', function () {
    ($this->tela)()->call('abrirImportacao', $this->parteDoColega->id)->assertForbidden();

    expect(Questao::query()->whereNotNull('enunciado')->count())->toBe(0);
});
