<?php

/*
 * O seeder do catálogo — o curso de Desenvolvimento de Sistemas como ele
 * é na escola.
 *
 * Catálogo, e não cenário: roda em produção, e por isso o que ele faz
 * tem de ser exatamente o que diz. Dois riscos importam aqui — duplicar
 * disciplina ao rodar de novo, e sobrescrever em silêncio um cadastro
 * que alguém já corrigiu pela tela.
 */

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Enums\StatusGrade;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\User;
use Database\Seeders\CursoDesenvolvimentoDeSistemasSeeder;

/** Roda o seeder sem o terminal, que nos testes não existe. */
function semearCatalogo(): void
{
    (new CursoDesenvolvimentoDeSistemasSeeder)->run();
}

it('cadastra o curso com as onze disciplinas, nos dois períodos', function () {
    semearCatalogo();

    $curso = Curso::query()->with('eixo')->where('codigo', 'DS')->sole();

    expect($curso->nome)->toBe('Desenvolvimento de Sistemas')
        ->and($curso->duracao_anos)->toBe(2)
        ->and($curso->eixo->codigo)->toBe('TEC');

    $porPeriodo = $curso->disciplinas()->get()->groupBy('periodo')->map->count();

    expect($curso->disciplinas()->count())->toBe(11)
        ->and($porPeriodo[1])->toBe(4)
        ->and($porPeriodo[2])->toBe(7);
});

it('grava os nomes como a escola os escreve', function () {
    semearCatalogo();

    $nomes = Disciplina::query()->orderBy('periodo')->orderBy('nome')->pluck('nome', 'codigo');

    expect($nomes['CCMT'])->toBe('Carreira e Competências para o Mercado de Trabalho')
        ->and($nomes['PDSMA'])->toBe('Processos de Desenvolvimento de Software e Metodologias Ágeis')
        ->and($nomes['RCSIN'])->toBe('Redes de Computadores e Segurança da Informação na Nuvem')
        ->and($nomes['PMDS'])->toBe('Projeto Multidisciplinar em Desenvolvimento de Sistemas')
        ->and($nomes['VCSM'])->toBe('Versionamento de Código e Sistemas de Mensageria');
});

/*
 * Rodar duas vezes é o caso normal: o comando é manual, e quem opera não
 * tem como saber se já rodou.
 */
it('pode ser rodado de novo sem duplicar nada', function () {
    semearCatalogo();
    semearCatalogo();

    expect(Eixo::query()->count())->toBe(1)
        ->and(Curso::query()->count())->toBe(1)
        ->and(Disciplina::query()->count())->toBe(11);
});

it('publica a grade quando há um PAEET no Eixo', function () {
    $eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação']);
    $admin = paeetAdmin($eixo);

    semearCatalogo();

    $grade = GradeCurricular::query()->sole();

    expect($grade->status)->toBe(StatusGrade::Vigente)
        ->and($grade->versao)->toBe(1)
        ->and($grade->criado_por)->toBe($admin->id)
        ->and($grade->disciplinas()->count())->toBe(11);
});

/*
 * Sem gestão cadastrada o catálogo entra assim mesmo. Publicar é ato de
 * alguém — fica no log, e a Policy exige escopo —, e criar usuário em
 * seed é o que este projeto não faz.
 */
it('cadastra as disciplinas mesmo sem ninguém para publicar a grade', function () {
    semearCatalogo();

    expect(Disciplina::query()->count())->toBe(11)
        ->and(GradeCurricular::query()->count())->toBe(0);
});

it('publica uma segunda versão quando o catálogo cresce', function () {
    $eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação']);
    $admin = paeetAdmin($eixo);

    // O curso já existia com uma disciplina, e já tinha grade publicada.
    $curso = Curso::query()->create([
        'eixo_id' => $eixo->id, 'codigo' => 'DS',
        'nome' => 'Desenvolvimento de Sistemas', 'duracao_anos' => 2,
    ]);

    Disciplina::query()->create([
        'curso_id' => $curso->id, 'codigo' => 'ESW',
        'nome' => 'Engenharia de Software', 'periodo' => 2, 'carga_horaria' => 60,
    ]);

    app(PublicarVersaoDeGradeAction::class)->executar($curso, $admin);

    semearCatalogo();

    $vigentes = GradeCurricular::query()->where('status', StatusGrade::Vigente)->get();

    // Nova versão, e não edição da vigente: turma aberta continua
    // apontando para a foto com que começou.
    expect(GradeCurricular::query()->count())->toBe(2)
        ->and($vigentes)->toHaveCount(1)
        ->and($vigentes->first()->versao)->toBe(2)
        ->and($vigentes->first()->disciplinas()->count())->toBe(12)
        ->and(GradeCurricular::query()->where('versao', 1)->sole()->status)
        ->toBe(StatusGrade::Arquivada);
});

/*
 * Restaurar não é mudar a grade: a disciplina volta com o mesmo id, e a
 * foto vigente já a listava. Publicar uma versão aqui seria registrar
 * uma mudança que não houve.
 */
it('restaura a disciplina excluída sem publicar versão nova', function () {
    $eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação']);
    paeetAdmin($eixo);

    semearCatalogo();

    Disciplina::query()->where('codigo', 'PMOB')->sole()->delete();

    semearCatalogo();

    $mobile = Disciplina::withTrashed()->where('codigo', 'PMOB')->sole();

    expect($mobile->trashed())->toBeFalse()
        ->and(Disciplina::query()->count())->toBe(11)
        ->and(GradeCurricular::query()->count())->toBe(1);
});

/*
 * O cadastro é a verdade viva; a lista aqui é um ponto de partida. Quem
 * moveu uma disciplina de período pela tela fez uma escolha acadêmica, e
 * um seed reexecutado não pode desfazê-la em silêncio.
 */
it('não sobrescreve o período de uma disciplina já cadastrada', function () {
    semearCatalogo();

    $mobile = Disciplina::query()->where('codigo', 'PMOB')->sole();
    $mobile->update(['periodo' => 1, 'nome' => 'Programação Mobile e Multiplataforma']);

    semearCatalogo();

    expect($mobile->refresh()->periodo)->toBe(1)
        ->and($mobile->nome)->toBe('Programação Mobile e Multiplataforma');
});

/*
 * O curso pode já existir com outras disciplinas — a demonstração, por
 * exemplo, cria oito. Apagar seria resolver no escuro: pode haver turma
 * cursando.
 */
it('mantém as disciplinas que já estavam no curso e fora da lista', function () {
    $eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação']);

    $curso = Curso::query()->create([
        'eixo_id' => $eixo->id, 'codigo' => 'DS',
        'nome' => 'Desenvolvimento de Sistemas', 'duracao_anos' => 2,
    ]);

    Disciplina::query()->create([
        'curso_id' => $curso->id, 'codigo' => 'ESW',
        'nome' => 'Engenharia de Software', 'periodo' => 2, 'carga_horaria' => 60,
    ]);

    semearCatalogo();

    expect(Disciplina::query()->count())->toBe(12)
        ->and(Disciplina::query()->where('codigo', 'ESW')->exists())->toBeTrue();
});

it('não cria usuário nenhum', function () {
    semearCatalogo();

    expect(User::query()->count())->toBe(0);
});

it('reaproveita o Eixo que já existir, em vez de criar outro', function () {
    $eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia da Informação']);

    semearCatalogo();

    expect(Eixo::query()->count())->toBe(1)
        ->and(Curso::query()->sole()->eixo_id)->toBe($eixo->id);
});

it('deixa a carga horária no padrão, que ainda será conferida', function () {
    semearCatalogo();

    expect(Disciplina::query()->pluck('carga_horaria')->unique()->all())->toBe([80]);
});

it('não entra no DatabaseSeeder, que é o da demonstração', function () {
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('CursoDesenvolvimentoDeSistemasSeeder');
});
