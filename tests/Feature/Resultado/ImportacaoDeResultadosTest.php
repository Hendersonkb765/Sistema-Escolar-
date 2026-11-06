<?php

/*
 * Importação dos resultados do leitor de folhas, em dois passos.
 *
 * O primeiro confere e **não grava nada**; o segundo grava só o que a
 * conferência aprovou. O cadastro dos alunos nunca é alterado: a
 * planilha só diz de quem é cada linha.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Exclusao\ExcluirRegistroAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\ConferirImportacaoAction;
use App\Actions\Resultado\ConfirmarImportacaoAction;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use App\Support\ConciliadorDeAlunos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
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
    $this->disciplinas = $montagem['disciplinas'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    // Dois alunos, para as coleções da tela nunca terem uma linha só.
    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves Coutinho', 'ra' => '1001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '1002']);

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->disciplinas['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
        ['disciplina' => $this->disciplinas['Redes'], 'professor' => $this->profRedes, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    $professores = [$this->profLogica, $this->profRedes];

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
        enviarParte($parte, $professores[$indice]);
    }

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Avaliação bimestral',
    );

    $this->prova->update(['status' => StatusProva::Aplicada]);

    /**
     * Escreve uma planilha no formato do leitor de folhas.
     *
     * @param  array<int, array<int, mixed>>  $linhas
     */
    $this->planilha = function (array $linhas, ?array $cabecalho = null): string {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();

        $aba->fromArray($cabecalho ?? [
            'Quiz Name', 'Class', 'ZipGrade Id', 'External Id', 'First Name', 'Last Name',
            'Num Questions', 'Num Correct', 'Percent Correct', 'Key Version',
            'Q1', 'Q2', 'Q3', 'Q4',
        ], null, 'A1');

        $aba->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'notas').'.xlsx';
        (new Xlsx($planilha))->save($caminho);

        $guardado = 'importacoes/'.basename($caminho);
        Storage::disk('local')->put($guardado, (string) file_get_contents($caminho));
        @unlink($caminho);

        return $guardado;
    };

    $this->importar = fn (string $arquivo) => Importacao::create([
        'prova_id' => $this->prova->id,
        'usuario_id' => $this->paeet->id,
        'arquivo' => $arquivo,
        'nome_original' => 'resultados.xlsx',
        'hash' => hash('sha256', $arquivo),
        'status' => StatusImportacao::Validando,
    ]);

    // Marina acerta tudo; Caio acerta só a primeira de cada disciplina.
    $this->duasLinhas = [
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A', 1, 1, 1, 1],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
    ];
});

/*
|--------------------------------------------------------------------------
| Passo 1: conferir não grava nada
|--------------------------------------------------------------------------
*/

it('reconhece os alunos sem gravar resultado nenhum', function () {
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    expect($importacao->refresh()->status)->toBe(StatusImportacao::Validada)
        ->and($importacao->total_linhas)->toBe(2)
        ->and($importacao->total_erros)->toBe(0)
        // Nada foi gravado: é só uma conferência.
        ->and(ResultadoAluno::query()->count())->toBe(0);

    $linhas = collect($importacao->relatorio['linhas']);

    expect($linhas->pluck('aluno')->all())
        ->toBe(['Marina Alves Coutinho', 'Caio Prado'])
        ->and($linhas->pluck('criterio')->all())
        ->toBe([ConciliadorDeAlunos::POR_RA, ConciliadorDeAlunos::POR_RA]);
});

it('reconhece pelo nome quando não há RA na planilha', function () {
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '', 'Marina Alves', 'Coutinho', 4, 4, 100, 'A', 1, 1, 1, 1],
        ['Prova', '1 A', '5002', '', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linhas = collect($importacao->refresh()->relatorio['linhas']);

    expect($linhas->pluck('aluno')->all())->toBe(['Marina Alves Coutinho', 'Caio Prado'])
        ->and($linhas->pluck('criterio')->all())
        ->toBe([ConciliadorDeAlunos::POR_NOME, ConciliadorDeAlunos::POR_NOME]);
});

it('reconhece pelo primeiro e último nome quando falta o do meio', function () {
    // O leitor costuma trazer o nome encurtado.
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '', 'Marina', 'Coutinho', 4, 4, 100, 'A', 1, 1, 1, 1],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linha = collect($importacao->refresh()->relatorio['linhas'])->first();

    expect($linha['aluno'])->toBe('Marina Alves Coutinho')
        ->and($linha['criterio'])->toBe(ConciliadorDeAlunos::POR_PRIMEIRO_E_ULTIMO);
});

it('ignora acento e caixa ao comparar os nomes', function () {
    Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Inês Gonçalves', 'ra' => '1003']);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5003', '', 'INES', 'GONCALVES', 4, 1, 25, 'A', 1, 0, 0, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    expect(collect($importacao->refresh()->relatorio['linhas'])->first()['aluno'])
        ->toBe('Inês Gonçalves');
});

it('recusa a linha de quem não está na turma, dizendo por quê', function () {
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '9999', '', 'Fulano', 'Inexistente', 4, 0, 0, 'A', 0, 0, 0, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linha = collect($importacao->refresh()->relatorio['linhas'])->first();

    expect($linha['erro'])->toContain('Nenhum aluno desta turma')
        ->and($linha['criterio'])->toBe(ConciliadorDeAlunos::NAO_ENCONTRADO)
        ->and($importacao->total_erros)->toBe(1);
});

it('recusa quando dois alunos da turma têm o mesmo nome', function () {
    Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Caio Prado', 'ra' => '1009']);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linha = collect($importacao->refresh()->relatorio['linhas'])->first();

    expect($linha['criterio'])->toBe(ConciliadorDeAlunos::AMBIGUO)
        ->and($linha['erro'])->toContain('Mais de um aluno')
        ->and($linha['candidatos'])->toHaveCount(2);
});

it('recusa o mesmo aluno duas vezes na mesma planilha', function () {
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 4, 100, 'A', 1, 1, 1, 1],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linhas = collect($importacao->refresh()->relatorio['linhas']);

    expect($linhas->first()['erro'])->toBeNull()
        ->and($linhas->last()['erro'])->toContain('já aparece na linha 2');
});

it('recusa a planilha sem as questões da prova', function () {
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 2, 1, 50, 'A', 1, 0],
    ], cabecalho: [
        'Quiz Name', 'Class', 'ZipGrade Id', 'External Id', 'First Name', 'Last Name',
        'Num Questions', 'Num Correct', 'Percent Correct', 'Key Version', 'Q1', 'Q2',
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    expect(collect($importacao->refresh()->relatorio['linhas'])->first()['erro'])
        ->toContain('não traz a(s) questão(ões) 3, 4');
});

it('avisa quando a contagem do leitor não bate com as colunas', function () {
    $importacao = ($this->importar)(($this->planilha)([
        // Diz 4 acertos, mas as colunas somam 2.
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 4, 100, 'A', 1, 0, 1, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linha = collect($importacao->refresh()->relatorio['linhas'])->first();

    expect($linha['erro'])->toBeNull()
        ->and($linha['avisos'][0])->toContain('informou 4 acerto(s)');
});

it('recusa planilha sem as colunas do leitor', function () {
    $importacao = ($this->importar)(($this->planilha)(
        [['Marina', 1, 1]],
        cabecalho: ['Aluno', 'Q1', 'Q2'],
    ));

    expect(fn () => app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet))
        ->toThrow(RegraDeNegocioException::class);
});

/*
|--------------------------------------------------------------------------
| Passo 2: confirmar grava o que foi aprovado
|--------------------------------------------------------------------------
*/

it('grava os acertos e calcula uma nota por disciplina', function () {
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    expect($importacao->refresh()->status)->toBe(StatusImportacao::Confirmada)
        ->and($importacao->confirmada_em)->not->toBeNull()
        ->and(ResultadoAluno::query()->count())->toBe(2);

    $caio = ResultadoAluno::query()
        ->where('aluno_id', $this->caio->id)
        ->with(['respostas', 'notas.disciplina'])
        ->sole();

    // Acertou a primeira de cada disciplina, errou a segunda.
    expect($caio->respostas)->toHaveCount(4)
        ->and($caio->respostas->where('acertou', true)->count())->toBe(2)
        // Duas disciplinas, duas notas independentes.
        ->and($caio->notas)->toHaveCount(2);

    foreach ($caio->notas as $nota) {
        expect((float) $nota->nota)->toBe(5.0);
    }

    $marina = ResultadoAluno::query()->where('aluno_id', $this->marina->id)->with('notas')->sole();

    foreach ($marina->notas as $nota) {
        expect((float) $nota->nota)->toBe(10.0);
    }
});

it('respeita o peso dentro da disciplina, e não na prova inteira', function () {
    // Lógica com pesos 1 e 3: errar a de peso 3 derruba a nota dela a
    // 2,5, sem mexer na nota de Redes.
    $logica = $this->prova->questoes()
        ->where('disciplina_id', $this->disciplinas['Lógica']->id)
        ->orderBy('numero')->get();

    $logica[0]->update(['peso' => 1]);
    $logica[1]->update(['peso' => 3]);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 3, 75, 'A', 1, 0, 1, 1],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    $notas = ResultadoAluno::query()
        ->where('aluno_id', $this->caio->id)
        ->with('notas.disciplina')
        ->sole()
        ->notas
        ->mapWithKeys(fn ($nota) => [$nota->disciplina->nome => (float) $nota->nota]);

    expect($notas['Lógica'])->toBe(2.5)
        ->and($notas['Redes'])->toBe(10.0);
});

it('não altera o cadastro do aluno', function () {
    $antes = $this->marina->only(['nome', 'ra', 'turma_id', 'status']);

    $importacao = ($this->importar)(($this->planilha)([
        // Nome encurtado na planilha: o do sistema não muda por isso.
        ['Prova', '1 A', '5001', '', 'Marina', 'Coutinho', 4, 4, 100, 'A', 1, 1, 1, 1],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    expect($this->marina->refresh()->only(['nome', 'ra', 'turma_id', 'status']))->toBe($antes);
});

it('deixa de fora as linhas recusadas', function () {
    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A', 1, 1, 1, 1],
        ['Prova', '1 A', '9999', '', 'Fulano', 'Inexistente', 4, 0, 0, 'A', 0, 0, 0, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    expect(ResultadoAluno::query()->count())->toBe(1)
        ->and(ResultadoAluno::query()->sole()->aluno_id)->toBe($this->marina->id);
});

it('recusa confirmar o que não foi conferido', function () {
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    expect(fn () => app(ConfirmarImportacaoAction::class)->executar($importacao, $this->paeet))
        ->toThrow(RegraDeNegocioException::class);

    expect(ResultadoAluno::query()->count())->toBe(0);
});

it('substitui o resultado anterior quando a mesma prova é reimportada', function () {
    $primeira = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 4, 100, 'A', 1, 1, 1, 1],
    ]));

    app(ConferirImportacaoAction::class)->executar($primeira, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($primeira->refresh(), $this->paeet);

    $segunda = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 0, 0, 'A', 0, 0, 0, 0],
    ]));

    app(ConferirImportacaoAction::class)->executar($segunda, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($segunda->refresh(), $this->paeet);

    // Vale a última leitura da folha, não a soma das duas.
    $resultado = ResultadoAluno::query()->where('aluno_id', $this->caio->id)->with(['respostas', 'notas'])->sole();

    expect(ResultadoAluno::query()->count())->toBe(1)
        ->and($resultado->respostas->where('acertou', true)->count())->toBe(0)
        ->and($resultado->notas->pluck('nota')->map(fn ($n) => (float) $n)->all())->toBe([0.0, 0.0]);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega a importação ao professor', function () {
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    expect(fn () => app(ConferirImportacaoAction::class)->executar($importacao, $this->profLogica))
        ->toThrow(AuthorizationException::class);
});

it('nega a importação de outro Eixo por id', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    expect(fn () => app(ConferirImportacaoAction::class)->executar($importacao, $forasteiro))
        ->toThrow(AuthorizationException::class);
});

/*
|--------------------------------------------------------------------------
| A planilha que traz a letra marcada
|--------------------------------------------------------------------------
|
| O leitor exporta de dois jeitos. No antigo, `Q1` vem como 1 ou 0 e o
| acerto já está decidido; no outro, vem a letra que o aluno marcou, e
| quem confere contra o gabarito é o sistema. É esse segundo formato que
| permite dizer, depois, qual alternativa errada atraiu a turma.
*/

/** O gabarito da prova, questão a questão. */
function gabarito(Prova $prova): array
{
    return ProvaQuestao::query()
        ->where('prova_id', $prova->getKey())
        ->orderBy('numero')
        ->pluck('letra_correta', 'numero')
        ->all();
}

/** Uma letra que existe na questão e não é a correta. */
function letraErrada(Prova $prova, int $numero): string
{
    $questao = ProvaQuestao::query()
        ->where('prova_id', $prova->getKey())
        ->where('numero', $numero)
        ->sole();

    return collect($questao->letrasValidas())
        ->first(fn (string $letra) => $letra !== $questao->letra_correta);
}

it('confere a letra marcada contra o gabarito da prova', function () {
    $certas = gabarito($this->prova);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A',
            $certas[1], $certas[2], $certas[3], $certas[4]],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 2, 50, 'A',
            $certas[1], letraErrada($this->prova, 2), $certas[3], letraErrada($this->prova, 4)],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $linhas = collect($importacao->refresh()->relatorio['linhas']);

    expect($linhas->firstWhere('linha', 2)['acertos'])->toBe(4)
        ->and($linhas->firstWhere('linha', 3)['acertos'])->toBe(2)
        // A contagem do leitor bate: nenhum aviso de divergência.
        ->and($linhas->pluck('avisos')->flatten())->toBeEmpty();
});

it('guarda a letra que o aluno marcou, inclusive a errada', function () {
    $certas = gabarito($this->prova);
    $erradaNa2 = letraErrada($this->prova, 2);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A',
            $certas[1], $certas[2], $certas[3], $certas[4]],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 3, 75, 'A',
            $certas[1], $erradaNa2, $certas[3], $certas[4]],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    $respostas = ResultadoAluno::query()
        ->where('aluno_id', $this->caio->id)
        ->with('respostas.provaQuestao:id,numero')
        ->sole()
        ->respostas
        ->keyBy(fn ($resposta) => $resposta->provaQuestao->numero);

    expect($respostas[2]->alternativa_marcada)->toBe($erradaNa2)
        ->and($respostas[2]->acertou)->toBeFalse()
        ->and($respostas[1]->alternativa_marcada)->toBe($certas[1])
        ->and($respostas[1]->acertou)->toBeTrue();
});

it('trata a questão em branco como erro, sem letra nenhuma', function () {
    $certas = gabarito($this->prova);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A',
            $certas[1], $certas[2], $certas[3], $certas[4]],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 3, 75, 'A',
            $certas[1], '', $certas[3], $certas[4]],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    $resposta = ResultadoAluno::query()
        ->where('aluno_id', $this->caio->id)
        ->with('respostas.provaQuestao:id,numero')
        ->sole()
        ->respostas
        ->first(fn ($resposta) => $resposta->provaQuestao->numero === 2);

    expect($resposta->alternativa_marcada)->toBeNull()
        ->and($resposta->acertou)->toBeFalse();
});

it('avisa quando a letra não existe entre as alternativas da questão', function () {
    $certas = gabarito($this->prova);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 3, 75, 'A',
            $certas[1], 'Z', $certas[3], $certas[4]],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 4, 100, 'A',
            $certas[1], $certas[2], $certas[3], $certas[4]],
    ]));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);

    $marina = collect($importacao->refresh()->relatorio['linhas'])->firstWhere('linha', 2);

    // Avisa, mas não recusa: a conferência é de quem olha a folha.
    expect($marina['erro'])->toBeNull()
        ->and($marina['avisos'])->toHaveCount(1)
        ->and($marina['avisos'][0])->toContain('A questão 2 não tem alternativa "Z"');
});

it('recusa a planilha que mistura letras e números nas questões', function () {
    $certas = gabarito($this->prova);

    $importacao = ($this->importar)(($this->planilha)([
        ['Prova', '1 A', '5001', '1001', 'Marina', 'Coutinho', 4, 4, 100, 'A',
            $certas[1], $certas[2], $certas[3], $certas[4]],
        ['Prova', '1 A', '5002', '1002', 'Caio', 'Prado', 4, 2, 50, 'A', 1, 0, 1, 0],
    ]));

    expect(fn () => app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'misturam letras e números');
});

it('não inventa letra nenhuma no formato de acerto e erro', function () {
    $importacao = ($this->importar)(($this->planilha)($this->duasLinhas));

    app(ConferirImportacaoAction::class)->executar($importacao, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($importacao->refresh(), $this->paeet);

    $respostas = ResultadoAluno::query()->with('respostas')->get()->pluck('respostas')->flatten();

    expect($respostas)->toHaveCount(8)
        ->and($respostas->pluck('alternativa_marcada')->unique()->all())->toBe([null]);
});

/*
 * A leitura errada vai para a Lixeira com os resultados que trouxe. Eles
 * continuam ocupando a chave (prova, aluno) no banco, e a nova leitura
 * precisa trazê-los de volta em vez de esbarrar neles.
 */
it('reimporta a prova depois de a importação anterior ir para a Lixeira', function () {
    $primeira = ($this->importar)(($this->planilha)($this->duasLinhas));
    app(ConferirImportacaoAction::class)->executar($primeira, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($primeira->refresh(), $this->paeet);

    app(ExcluirRegistroAction::class)->executar($primeira->refresh(), $this->paeet);

    expect($this->prova->resultados()->count())->toBe(0);

    $segunda = ($this->importar)(($this->planilha)($this->duasLinhas));
    app(ConferirImportacaoAction::class)->executar($segunda, $this->paeet);
    app(ConfirmarImportacaoAction::class)->executar($segunda->refresh(), $this->paeet);

    expect($this->prova->resultados()->count())->toBe(2)
        ->and($this->prova->resultados()->pluck('importacao_id')->unique()->all())->toBe([$segunda->id]);
});
