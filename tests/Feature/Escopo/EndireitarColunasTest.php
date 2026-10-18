<?php

/*
 * Endireitar as colunas giradas da planilha do escopo-sequência.
 *
 * No arquivo que a rede publicou, parte das linhas veio com três
 * colunas rodadas uma casa: a competência técnica trazia o NOME do
 * componente, a socioemocional trazia a técnica, e o nome trazia a
 * socioemocional.
 *
 * O conserto não é adivinhado: é testado. Um código identifica um
 * componente, então o nome tem de ser o mesmo em todas as linhas
 * daquele código — e o giro só é aplicado quando resolve isso. Quando
 * não resolve, as linhas ficam como estavam.
 */

use App\Exceptions\RegraDeNegocioException;
use App\Support\EndireitaColunasDoEscopo;
use App\Support\PlanilhaDeEscopo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->destino = tempnam(sys_get_temp_dir(), 'endireitado').'.xlsx';

    /**
     * Escreve uma planilha do escopo.
     *
     * @param  array<int, array<int, mixed>>  $linhas
     */
    $this->arquivo = function (array $linhas): string {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();
        $aba->fromArray(CABECALHO_DO_ESCOPO, null, 'A1');
        $aba->fromArray($linhas, null, 'A2');

        $caminho = tempnam(sys_get_temp_dir(), 'escopo').'.xlsx';
        (new Xlsx($planilha))->save($caminho);

        return $caminho;
    };

    /** Uma linha certa: nome no nome, técnica na técnica, socio na socio. */
    $this->certa = fn (array $campos = []) => linhaDoEscopo(array_merge([
        'Nome do componente' => 'Programação Mobile',
        'Código componente' => 'C1',
        'Competência técnica' => 'Desenvolver aplicações móveis',
        'Competências socioemocionais' => 'Curiosidade: interessar-se por ideias novas',
    ], $campos));

    /** A mesma linha, com as três colunas giradas uma casa. */
    $this->girada = fn (array $campos = []) => linhaDoEscopo(array_merge([
        'Competência técnica' => 'Programação Mobile',
        'Competências socioemocionais' => 'Desenvolver aplicações móveis',
        'Nome do componente' => 'Curiosidade: interessar-se por ideias novas',
        'Código componente' => 'C1',
    ], $campos));

    /** Lê a cópia endireitada de volta. */
    $this->lerDestino = function (): array {
        $leitor = IOFactory::createReaderForFile($this->destino);
        $leitor->setReadDataOnly(true);

        $celulas = $leitor->load($this->destino)->getActiveSheet()->toArray(null, true, false, false);
        $cabecalho = array_map(
            fn ($c) => PlanilhaDeEscopo::normalizar((string) $c),
            array_shift($celulas)
        );

        return array_map(fn (array $linha) => array_combine($cabecalho, $linha), $celulas);
    };
});

it('desfaz o giro das três colunas', function () {
    $origem = ($this->arquivo)([
        ($this->girada)(['Semana' => 1, 'Título da aula' => 'Aula 1']),
        ($this->girada)(['Semana' => 2, 'Título da aula' => 'Aula 2',
            'Nome do componente' => 'Persistência: perseverar até concluir']),
    ]);

    $resultado = EndireitaColunasDoEscopo::em($origem, $this->destino);

    expect($resultado->linhasTocadas)->toBe(2)
        ->and($resultado->corrigidos)->toHaveKey('C1')
        ->and($resultado->corrigidos['C1']['para'])->toBe('Programação Mobile')
        ->and($resultado->naoResolvidos)->toBe([]);

    $linhas = ($this->lerDestino)();

    foreach ($linhas as $linha) {
        expect($linha['nome do componente'])->toBe('Programação Mobile')
            ->and($linha['competencia tecnica'])->toBe('Desenvolver aplicações móveis');
    }

    // A socioemocional de cada aula volta para a coluna dela.
    expect($linhas[0]['competencias socioemocionais'])->toBe('Curiosidade: interessar-se por ideias novas')
        ->and($linhas[1]['competencias socioemocionais'])->toBe('Persistência: perseverar até concluir');
});

it('não encosta no código que já está certo', function () {
    $origem = ($this->arquivo)([
        ($this->certa)(['Semana' => 1]),
        ($this->certa)(['Semana' => 2, 'Competências socioemocionais' => 'Outra socioemocional']),
        ($this->girada)(['Código componente' => 'C5', 'Competência técnica' => 'Inteligência Artificial']),
        ($this->girada)(['Código componente' => 'C5', 'Semana' => 2,
            'Competência técnica' => 'Inteligência Artificial',
            'Nome do componente' => 'Outra socioemocional ainda']),
    ]);

    $resultado = EndireitaColunasDoEscopo::em($origem, $this->destino);

    expect(array_keys($resultado->corrigidos))->toBe(['C5'])
        ->and($resultado->linhasTocadas)->toBe(2);

    $linhas = collect(($this->lerDestino)())->groupBy('codigo componente');

    expect($linhas['C1']->pluck('nome do componente')->unique()->all())->toBe(['Programação Mobile'])
        ->and($linhas['C5']->pluck('nome do componente')->unique()->all())->toBe(['Inteligência Artificial']);
});

/*
 * O giro é uma hipótese, e ela é verificada antes de ser aplicada.
 * Quando girar não deixa o código com um nome só, o problema é outro —
 * e mexer nas linhas seria trocar um erro por outro.
 */
it('deixa como está quando o giro não resolve', function () {
    $origem = ($this->arquivo)([
        ($this->certa)(['Semana' => 1, 'Nome do componente' => 'Um nome']),
        ($this->certa)(['Semana' => 2, 'Nome do componente' => 'Outro nome',
            'Competência técnica' => 'Técnica diferente também']),
    ]);

    $resultado = EndireitaColunasDoEscopo::em($origem, $this->destino);

    expect($resultado->corrigidos)->toBe([])
        ->and($resultado->naoResolvidos)->toBe(['C1' => 2])
        ->and($resultado->linhasTocadas)->toBe(0)
        // Sem nada a gravar, não há cópia: um arquivo igual ao original
        // só faria alguém importar o errado achando que corrigiu.
        ->and(file_exists($this->destino) && filesize($this->destino) > 0)->toBeFalse();
});

it('não faz nada quando a planilha já está certa', function () {
    $origem = ($this->arquivo)([
        ($this->certa)(['Semana' => 1]),
        ($this->certa)(['Semana' => 2]),
    ]);

    $resultado = EndireitaColunasDoEscopo::em($origem, $this->destino);

    expect($resultado->corrigidos)->toBe([])
        ->and($resultado->naoResolvidos)->toBe([])
        ->and($resultado->linhasTocadas)->toBe(0);
});

it('nunca altera o arquivo original', function () {
    $origem = ($this->arquivo)([($this->girada)(), ($this->girada)(['Semana' => 2,
        'Nome do componente' => 'Outra socioemocional'])]);

    $antes = md5_file($origem);

    EndireitaColunasDoEscopo::em($origem, $this->destino);

    expect(md5_file($origem))->toBe($antes);
});

it('recusa a planilha sem as colunas que giram', function () {
    $origem = ($this->arquivo)([($this->girada)()]);

    // Reescreve só com duas colunas.
    $planilha = new Spreadsheet;
    $planilha->getActiveSheet()->fromArray(['Bimestre', 'Semana'], null, 'A1');
    (new Xlsx($planilha))->save($origem);

    expect(fn () => EndireitaColunasDoEscopo::em($origem, $this->destino))
        ->toThrow(RegraDeNegocioException::class, 'Competência técnica');
});

it('recusa arquivo que não existe', function () {
    expect(fn () => EndireitaColunasDoEscopo::em('/nao/existe.xlsx', $this->destino))
        ->toThrow(RegraDeNegocioException::class, 'não encontrado');
});

/*
|--------------------------------------------------------------------------
| O comando
|--------------------------------------------------------------------------
*/

it('endireita pelo terminal e diz o que mudou', function () {
    $origem = ($this->arquivo)([
        ($this->girada)(['Semana' => 1]),
        ($this->girada)(['Semana' => 2, 'Nome do componente' => 'Outra socioemocional']),
    ]);

    $this->artisan('escopo:endireitar', ['arquivo' => $origem, '--saida' => $this->destino])
        ->expectsOutputToContain('C1')
        ->expectsOutputToContain('Programação Mobile')
        ->expectsOutputToContain('2 linha(s) endireitada(s)')
        ->expectsOutputToContain('O original não foi alterado')
        ->assertSuccessful();

    expect(file_exists($this->destino))->toBeTrue();
});

it('avisa pelo terminal quando não há o que endireitar', function () {
    $origem = ($this->arquivo)([($this->certa)(['Semana' => 1]), ($this->certa)(['Semana' => 2])]);

    $this->artisan('escopo:endireitar', ['arquivo' => $origem, '--saida' => $this->destino])
        ->expectsOutputToContain('Nada a endireitar')
        ->assertSuccessful();
});

it('falha pelo terminal quando o giro não resolve', function () {
    $origem = ($this->arquivo)([
        ($this->certa)(['Semana' => 1, 'Nome do componente' => 'Um nome']),
        ($this->certa)(['Semana' => 2, 'Nome do componente' => 'Outro nome',
            'Competência técnica' => 'Técnica diferente']),
    ]);

    $this->artisan('escopo:endireitar', ['arquivo' => $origem, '--saida' => $this->destino])
        ->expectsOutputToContain('o giro das três colunas não resolve')
        ->assertFailed();
});

it('grava ao lado do original quando não se diz onde', function () {
    $origem = ($this->arquivo)([
        ($this->girada)(['Semana' => 1]),
        ($this->girada)(['Semana' => 2, 'Nome do componente' => 'Outra socioemocional']),
    ]);

    $esperado = dirname($origem).'/'.pathinfo($origem, PATHINFO_FILENAME).'-endireitado.xlsx';

    $this->artisan('escopo:endireitar', ['arquivo' => $origem])->assertSuccessful();

    expect(file_exists($esperado))->toBeTrue();

    @unlink($esperado);
});
