<?php

/*
 * O que o professor enxerga de turma, curso e aluno.
 *
 * Os três escopos filtravam por `solicitacoes.professor_id`, e essa
 * coluna não existe: quem responde uma solicitação é a **parte**, não a
 * solicitação. No SQLite o defeito é mudo — um identificador entre aspas
 * que não resolve para coluna nenhuma vira texto literal, e
 * `'professor_id' = 5` é simplesmente falso. No MySQL seria "Unknown
 * column" e a tela estouraria.
 *
 * Resultado: o professor via zero turmas, zero cursos e zero alunos, sem
 * mensagem de erro em lugar nenhum — o filtro de turma da tela de notas
 * abria vazio.
 */

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\Turma;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->estranho = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->curso = $montagem['curso'];

    $this->turma = Turma::factory()->doCurso($this->curso, $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->aluno = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Marina Alves']);

    solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->professor, 'questoes' => 2],
    ]);
});

it('enxerga a turma em que tem parte numa solicitação', function () {
    expect(Turma::query()->visivelPara($this->professor)->pluck('id')->all())
        ->toBe([$this->turma->id]);
});

it('enxerga o curso dessa turma', function () {
    expect(Curso::query()->visivelPara($this->professor)->pluck('id')->all())
        ->toBe([$this->curso->id]);
});

it('enxerga os alunos dessa turma', function () {
    expect(Aluno::query()->visivelPara($this->professor)->pluck('id')->all())
        ->toBe([$this->aluno->id]);
});

/*
 * O escopo continua sendo um escopo: quem não foi designado não passa a
 * ver a turma só porque a consulta foi corrigida.
 */
it('não enxerga nada quando não foi designado', function () {
    expect(Turma::query()->visivelPara($this->estranho)->count())->toBe(0)
        ->and(Curso::query()->visivelPara($this->estranho)->count())->toBe(0)
        ->and(Aluno::query()->visivelPara($this->estranho)->count())->toBe(0);
});

it('não enxerga turma de outro curso do mesmo Eixo', function () {
    $outro = cursoComGrade($this->eixo, [1 => ['Contabilidade']], autor: $this->paeet);
    Turma::factory()->doCurso($outro['curso'], $outro['grade'])
        ->create(['periodo' => 1, 'nome' => '1 Z']);

    expect(Turma::query()->visivelPara($this->professor)->pluck('id')->all())
        ->toBe([$this->turma->id]);
});
