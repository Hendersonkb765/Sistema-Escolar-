<?php

/*
 * O gabarito no CSV que os leitores de folha de respostas importam:
 *
 *     Key Letter,Question Number,Response/Mapping,Point Value,Tags
 *
 * Regras do formato conferidas aqui:
 *
 * - Key Letter em branco significa a chave primária, que é a única que
 *   a prova tem;
 * - Question Number é inteiro e segue a numeração contínua da prova;
 * - Response/Mapping é a letra correta, com aspas se contiver vírgula;
 * - Point Value usa ponto como separador decimal;
 * - Tags é opcional e sai vazia;
 * - uma linha por resposta aceita, e cada trio (versão, questão,
 *   resposta) aparece uma vez só.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\GerarGabaritoCsvAction;
use App\Actions\Prova\MontarProvaAction;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'criado_por' => $this->paeet->id,
    ]);

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

    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
    );

    $this->csv = fn () => app(GerarGabaritoCsvAction::class)->conteudo($this->prova, $this->paeet);

    /** Lê o CSV de volta, como o leitor de folhas faria. */
    $this->registros = function (string $csv): array {
        $linhas = [];
        $arquivo = fopen('php://temp', 'r+');
        fwrite($arquivo, $csv);
        rewind($arquivo);

        while (($linha = fgetcsv($arquivo, escape: '')) !== false) {
            $linhas[] = $linha;
        }

        fclose($arquivo);

        return $linhas;
    };
});

it('abre com o cabeçalho exato do formato', function () {
    expect(($this->csv)())->toStartWith(
        "Key Letter,Question Number,Response/Mapping,Point Value,Tags\r\n"
    );
});

it('escreve uma linha por questão, na numeração da prova', function () {
    $registros = ($this->registros)(($this->csv)());

    // Cabeçalho + as quatro questões.
    expect($registros)->toHaveCount(5)
        ->and(array_shift($registros))->toBe(GerarGabaritoCsvAction::CABECALHO);

    expect(array_column($registros, 1))->toBe(['1', '2', '3', '4']);
});

it('deixa a versão da chave em branco, que é a chave primária', function () {
    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    expect(array_column($registros, 0))->toBe(['', '', '', '']);
});

it('leva a letra correta de cada questão, vinda do snapshot', function () {
    $esperadas = $this->prova->questoes()->orderBy('numero')->pluck('letra_correta')->all();

    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    expect(array_column($registros, 2))->toBe($esperadas)
        ->and($esperadas)->each->toBeIn(['A', 'B', 'C', 'D', 'E']);
});

it('usa ponto como separador decimal no valor em pontos', function () {
    $this->prova->questoes()->where('numero', 1)->update(['peso' => 0.75]);
    $this->prova->questoes()->where('numero', 2)->update(['peso' => 1.5]);

    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    expect(array_column($registros, 3))->toBe(['0.75', '1.50', '1.00', '1.00']);
});

it('deixa as etiquetas vazias, que são opcionais', function () {
    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    expect(array_column($registros, 4))->toBe(['', '', '', '']);
});

it('não repete o trio versão, questão e resposta', function () {
    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    $trios = array_map(fn (array $linha) => implode('|', array_slice($linha, 0, 3)), $registros);

    expect($trios)->toBe(array_unique($trios));
});

it('escreve uma linha extra para cada resposta alternativa', function () {
    // O formato prevê mais de uma resposta aceita por questão.
    $primeira = $this->prova->questoes()->where('numero', 1)->sole();

    $primeira->update(['alternativas_snapshot' => collect($primeira->alternativas_snapshot)
        ->map(fn (array $a) => [...$a, 'correta' => in_array($a['letra'], ['A', 'C'], true)])
        ->all()]);

    $registros = ($this->registros)(($this->csv)());
    array_shift($registros);

    $daPrimeira = array_values(array_filter($registros, fn (array $l) => $l[1] === '1'));

    expect($daPrimeira)->toHaveCount(2)
        ->and(array_column($daPrimeira, 2))->toBe(['A', 'C']);
});

it('põe entre aspas a resposta que contém vírgula', function () {
    $primeira = $this->prova->questoes()->where('numero', 1)->sole();

    $primeira->update(['alternativas_snapshot' => [
        ['letra' => 'A,B', 'texto' => 'Duas de uma vez', 'correta' => true],
    ]]);

    expect(($this->csv)())->toContain(',"A,B",');

    // E volta a ser um campo só na leitura.
    $registros = ($this->registros)(($this->csv)());

    expect($registros[1][2])->toBe('A,B');
});

/*
|--------------------------------------------------------------------------
| Avisos: o tamanho da folha é de quem imprime
|--------------------------------------------------------------------------
*/

it('não avisa nada quando a prova cabe na folha de referência', function () {
    expect(app(GerarGabaritoCsvAction::class)->avisos($this->prova))->toBe([]);
});

it('avisa quando a resposta sai das letras da folha', function () {
    $primeira = $this->prova->questoes()->where('numero', 1)->sole();

    $primeira->update(['alternativas_snapshot' => [
        ['letra' => 'E', 'texto' => 'Quinta alternativa', 'correta' => true],
    ]]);

    expect(app(GerarGabaritoCsvAction::class)->avisos($this->prova))
        ->toHaveCount(1)
        ->and(app(GerarGabaritoCsvAction::class)->avisos($this->prova)[0])
        ->toContain('questão 1')
        ->toContain('A, B, C, D');
});

it('avisa quando a questão não tem correta marcada, e sai em branco', function () {
    $primeira = $this->prova->questoes()->where('numero', 1)->sole();

    $primeira->update(['alternativas_snapshot' => [
        ['letra' => 'A', 'texto' => 'Nenhuma marcada', 'correta' => false],
    ]]);

    $avisos = app(GerarGabaritoCsvAction::class)->avisos($this->prova);

    expect($avisos)->toHaveCount(1)
        ->and($avisos[0])->toContain('não tem alternativa correta');

    $registros = ($this->registros)(($this->csv)());

    expect($registros[1][2])->toBe('');
});

it('avisa quando a prova passa do tamanho da folha de referência', function () {
    // Uma segunda solicitação enche a turma de questões aprovadas: a
    // prova montada depois passa das 20 que a folha comporta.
    $logica = $this->turma->curso->disciplinas()->where('nome', 'Lógica')->sole();

    $extra = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $logica, 'professor' => $this->profLogica, 'questoes' => 20],
    ], titulo: 'Reforço');

    enviarParte($extra->partes()->sole(), $this->profLogica);

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($extra->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $grande = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação longa',
    );

    expect($grande->totalDeQuestoes())->toBeGreaterThan(GerarGabaritoCsvAction::QUESTOES_NA_FOLHA);

    $avisos = app(GerarGabaritoCsvAction::class)->avisos($grande);

    expect($avisos)->toHaveCount(1)
        ->and($avisos[0])
        ->toContain("{$grande->totalDeQuestoes()} questões")
        ->toContain('tem '.GerarGabaritoCsvAction::QUESTOES_NA_FOLHA);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega o gabarito ao professor que tem questão na prova', function () {
    expect(fn () => app(GerarGabaritoCsvAction::class)->conteudo($this->prova, $this->profLogica))
        ->toThrow(AuthorizationException::class);
});

it('nega o gabarito por id na URL a quem é de outro Eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => app(GerarGabaritoCsvAction::class)->conteudo($this->prova, $forasteiro))
        ->toThrow(AuthorizationException::class);
});
