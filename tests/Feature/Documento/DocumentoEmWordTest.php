<?php

/*
 * O mesmo documento, em Word.
 *
 * O .docx é um zip de XML, então o que se afirma aqui é o que dá para
 * ler de dentro dele: que o texto de cada aluno está lá, que o negrito
 * atravessou o campo, e que a quebra de página aparece a cada N vias.
 */

use App\Actions\Documento\GerarDocxDoDocumentoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\ModeloDocumento;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->alunos = collect(['Marina Alves', 'Caio Prado', 'Rita Souza'])
        ->map(fn (string $nome, int $i) => Aluno::factory()->naTurma($this->turma)
            ->create(['nome' => $nome, 'ra' => '2026100'.$i]));

    $this->acao = app(GerarDocxDoDocumentoAction::class);
    $this->todos = fn () => $this->alunos->pluck('id')->all();
});

/** O document.xml de dentro do .docx. */
function xmlDoDocx(string $docx): string
{
    $arquivo = tempnam(sys_get_temp_dir(), 'teste').'.docx';
    file_put_contents($arquivo, $docx);

    $zip = new ZipArchive;
    $zip->open($arquivo);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    @unlink($arquivo);

    return $xml;
}

/** O texto corrido do .docx, sem as tags. */
function textoDoDocx(string $docx): string
{
    return html_entity_decode(strip_tags(str_replace('<', ' <', xmlDoDocx($docx))));
}

it('gera um .docx de verdade', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    $docx = $this->acao->conteudo($modelo, $this->turma, ($this->todos)(), $this->paeet);

    expect($docx)->toStartWith('PK')
        ->and(xmlDoDocx($docx))->toContain('<w:document');
});

it('põe uma via por aluno escolhido, com o nome de cada um', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    $texto = textoDoDocx($this->acao->conteudo(
        $modelo, $this->turma, [$this->alunos[0]->id, $this->alunos[2]->id], $this->paeet
    ));

    expect($texto)->toContain('Marina Alves')
        ->toContain('Rita Souza')
        ->not->toContain('Caio Prado');
});

it('quebra a página a cada N vias', function () {
    $contarQuebras = fn (int $porPagina) => substr_count(
        xmlDoDocx($this->acao->conteudo(
            ModeloDocumento::factory()->noEixo($this->eixo)->porPagina($porPagina)->create(),
            $this->turma,
            ($this->todos)(),
            $this->paeet,
        )),
        'w:br w:type="page"',
    );

    // Três alunos: uma via por página dá duas quebras; três por página,
    // nenhuma.
    expect($contarQuebras(1))->toBe(2)
        ->and($contarQuebras(3))->toBe(0);
});

/*
 * O negrito envolvendo o campo é o caso que o PDF revelou. Vale para o
 * Word pelo mesmo caminho: a troca do campo vem antes da formatação.
 */
it('aplica negrito ao campo envolvido pelas marcas', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create([
        'corpo' => 'Aluno **{{ aluno.nome }}** confirmado.',
    ]);

    $xml = xmlDoDocx($this->acao->conteudo($modelo, $this->turma, [$this->alunos[0]->id], $this->paeet));

    /*
     * O nome tem de estar DENTRO de um trecho marcado como negrito — daí
     * o "sem fechar o <w:r> no meio". Procurar só a proximidade passaria
     * com o negrito num trecho vizinho, que é justamente o defeito.
     */
    expect($xml)->toMatch('/<w:b w:val="1"\/>(?:(?!<\/w:r>).)*Marina Alves/s')
        ->and(textoDoDocx($this->acao->conteudo($modelo, $this->turma, [$this->alunos[0]->id], $this->paeet)))
        ->not->toContain('**');
});

it('escreve a lista da turma no documento coletivo', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->coletivo()->create();

    $texto = textoDoDocx($this->acao->conteudo($modelo, $this->turma, ($this->todos)(), $this->paeet));

    expect($texto)->toContain('Marina Alves')
        ->toContain('Caio Prado')
        ->toContain('Rita Souza')
        ->toContain('Assinatura');
});

it('desenha os campos de preencher', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create([
        'corpo' => 'Eu, {{ linha: nome }}, {{ caixa: Autorizo }} {{ assinatura: Responsável }}',
    ]);

    $texto = textoDoDocx($this->acao->conteudo($modelo, $this->turma, [$this->alunos[0]->id], $this->paeet));

    expect($texto)->toContain('____')
        ->toContain('☐')
        ->toContain('Autorizo')
        ->toContain('Responsável');
});

it('leva o nome da escola para cada via, porque a folha é recortada', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->porPagina(3)->create();

    $texto = textoDoDocx($this->acao->conteudo($modelo, $this->turma, ($this->todos)(), $this->paeet));

    expect(substr_count($texto, (string) config('instituicao.nome')))->toBe(3);
});

it('dá ao arquivo o nome certo, com a extensão certa', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create(['nome' => 'Autorização de saída']);

    expect($this->acao->nomeDoArquivo($modelo, $this->turma))->toBe('autorizacao-de-saida-1-a.docx');
});

it('recusa gerar sem aluno escolhido', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->acao->conteudo($modelo, $this->turma, [], $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'pelo menos um aluno');
});

it('recusa o modelo com campo do outro tipo, como o PDF', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->coletivo()->create([
        'corpo' => 'Autorizo {{ aluno.nome }}.',
    ]);

    expect(fn () => $this->acao->conteudo($modelo, $this->turma, ($this->todos)(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'aluno.nome');
});

/*
 * A autorização não pode valer só no PDF: uma brecha no formato menos
 * usado demora mais para aparecer.
 */
it('nega o Word de modelo de outro Eixo', function () {
    $alheio = ModeloDocumento::factory()->noEixo(Eixo::factory()->create(['codigo' => 'ADM']))->create();

    expect(fn () => $this->acao->conteudo($alheio, $this->turma, ($this->todos)(), $this->paeet))
        ->toThrow(AuthorizationException::class);
});

it('nega o Word ao professor', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->acao->conteudo($modelo, $this->turma, ($this->todos)(), professor($this->eixo)))
        ->toThrow(AuthorizationException::class);
});

it('ignora o id de aluno que não é da turma', function () {
    $outra = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 B']);
    $deFora = Aluno::factory()->naTurma($outra)->create(['nome' => 'Intruso Silva']);

    $texto = textoDoDocx($this->acao->conteudo(
        ModeloDocumento::factory()->noEixo($this->eixo)->create(),
        $this->turma,
        [$this->alunos[0]->id, $deFora->id],
        $this->paeet,
    ));

    expect($texto)->toContain('Marina Alves')->not->toContain('Intruso Silva');
});

/*
 * O PhpWord 1.4 no PHP 8.5 emite E_DEPRECATED ao escrever cada
 * parágrafo. O silêncio é estreito de propósito — quando a biblioteca
 * corrigir, este teste é que vai cobrar a saída do remendo.
 */
it('não deixa vazar depreciação do PhpWord no download', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    $avisos = [];
    set_error_handler(function (int $tipo, string $mensagem) use (&$avisos) {
        $avisos[] = $mensagem;

        return true;
    }, E_DEPRECATED);

    try {
        $this->acao->conteudo($modelo, $this->turma, ($this->todos)(), $this->paeet);
    } finally {
        restore_error_handler();
    }

    expect($avisos)->toBe([]);
});
