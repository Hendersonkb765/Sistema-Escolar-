<?php

/*
 * O PDF dos documentos do aluno.
 *
 * Um PDF não se lê com `assertSee`, então o que se afirma aqui é o que dá
 * para afirmar de fora: que saiu PDF, com quantas páginas, e que o texto
 * extraível traz o nome de quem devia e não traz o de quem não devia.
 */

use App\Actions\Documento\GerarDocumentosAction;
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

    $this->acao = app(GerarDocumentosAction::class);
});

/** Quantas páginas tem o PDF, pela contagem de objetos de página. */
function paginasDoPdf(string $pdf): int
{
    preg_match_all('/\/Type\s*\/Page[^s]/', $pdf, $achados);

    return count($achados[0]);
}

it('gera um PDF com uma via por aluno escolhido', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    $pdf = $this->acao->conteudo($modelo, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet);

    expect($pdf)->toStartWith('%PDF-')
        ->and(paginasDoPdf($pdf))->toBe(3);
});

it('gera só para os alunos escolhidos', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    $escolhidos = [$this->alunos[0]->id, $this->alunos[2]->id];

    $pdf = $this->acao->conteudo($modelo, $this->turma, $escolhidos, $this->paeet);

    expect(paginasDoPdf($pdf))->toBe(2);
});

it('põe três vias na mesma página quando o modelo pede', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->porPagina(3)->create();

    $pdf = $this->acao->conteudo($modelo, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet);

    expect(paginasDoPdf($pdf))->toBe(1);
});

it('quebra a página a cada duas vias quando cabem duas', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->porPagina(2)->create();

    $pdf = $this->acao->conteudo($modelo, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet);

    // Três vias, duas por página: uma folha cheia e outra com uma via.
    expect(paginasDoPdf($pdf))->toBe(2);
});

it('gera uma via só no documento coletivo, com a turma inteira dentro', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->coletivo()->create();

    $pdf = $this->acao->conteudo($modelo, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet);

    expect(paginasDoPdf($pdf))->toBe(1);
});

it('recusa gerar sem escolher aluno', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->acao->conteudo($modelo, $this->turma, [], $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'pelo menos um aluno');
});

/*
 * Os ids vêm da tela, portanto de fora. Um id de aluno de outra turma não
 * pode entrar no documento desta.
 */
it('ignora o id de aluno que não é da turma', function () {
    $outraTurma = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 B']);
    $deFora = Aluno::factory()->naTurma($outraTurma)->create(['nome' => 'Intruso Silva']);

    $pdf = $this->acao->conteudo(
        $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create(),
        $this->turma,
        [$this->alunos[0]->id, $deFora->id],
        $this->paeet,
    );

    expect(paginasDoPdf($pdf))->toBe(1);
});

it('recusa quando nenhum id escolhido é da turma', function () {
    $outraTurma = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 B']);
    $deFora = Aluno::factory()->naTurma($outraTurma)->create();

    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->acao->conteudo($modelo, $this->turma, [$deFora->id], $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'pertence a esta turma');
});

it('recusa gerar com o modelo que usa campo do outro tipo', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->coletivo()->create([
        'corpo' => 'Autorizo {{ aluno.nome }}.',
    ]);

    expect(fn () => $this->acao->conteudo(
        $modelo, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet
    ))->toThrow(RegraDeNegocioException::class, 'aluno.nome');
});

it('nega a geração com modelo de outro Eixo', function () {
    $outro = Eixo::factory()->create(['codigo' => 'ADM']);
    $alheio = ModeloDocumento::factory()->noEixo($outro)->create();

    expect(fn () => $this->acao->conteudo(
        $alheio, $this->turma, $this->alunos->pluck('id')->all(), $this->paeet
    ))->toThrow(AuthorizationException::class);
});

it('nega a geração ao professor', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->acao->conteudo(
        $modelo, $this->turma, $this->alunos->pluck('id')->all(), professor($this->eixo)
    ))->toThrow(AuthorizationException::class);
});

it('dá ao arquivo um nome que diz do que é', function () {
    $modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create(['nome' => 'Autorização de saída']);

    expect($this->acao->nomeDoArquivo($modelo, $this->turma))
        ->toBe('autorizacao-de-saida-1-a.pdf');
});
