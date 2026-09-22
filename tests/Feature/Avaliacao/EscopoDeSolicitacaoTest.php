<?php

/*
 * As três barreiras que todo recurso escopado precisa provar:
 * professor não cria, PAEET de outro Eixo recebe 403 por id (IDOR), e a
 * listagem não vaza o que é de fora.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Models\Eixo;
use App\Models\SolicitacaoProva;
use App\Models\Turma;

beforeEach(function () {
    $this->tecnologia = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->gestao = Eixo::factory()->create(['nome' => 'Gestão', 'codigo' => 'ADM']);

    $this->paeetTecnologia = paeet($this->tecnologia);
    $this->paeetGestao = paeet($this->gestao);

    $criar = app(CriarSolicitacaoAction::class);

    $montar = function (Eixo $eixo, $paeet, string $disciplina) use ($criar) {
        $montagem = cursoComGrade($eixo, [1 => [$disciplina]], duracaoAnos: 2, autor: $paeet);

        $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
            'periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026',
        ]);

        $professor = professor($eixo);

        return $criar->executar(
            autor: $paeet, turma: $turma, disciplina: $montagem['disciplinas'][$disciplina],
            professor: $professor, quantidadeQuestoes: 2, quantidadeAlternativas: 4,
            prazo: now()->addWeek(),
        );
    };

    $this->minha = $montar($this->tecnologia, $this->paeetTecnologia, 'Lógica de Programação');
    $this->alheia = $montar($this->gestao, $this->paeetGestao, 'Contabilidade Geral');

    $this->professor = professor($this->tecnologia);
});

it('responde 403 ao professor nas rotas de criação', function () {
    $this->actingAs($this->professor)->get(route('solicitacoes.criar'))->assertForbidden();

    expect($this->professor->can('create', SolicitacaoProva::class))->toBeFalse();
});

it('responde 403 em solicitação de outro eixo acessada por id', function (string $rota) {
    $this->actingAs($this->paeetTecnologia)
        ->get(route($rota, $this->alheia))
        ->assertForbidden();
})->with(['solicitacoes.show', 'solicitacoes.responder']);

it('responde 403 na URL montada à mão com o id de outro eixo', function () {
    $this->actingAs($this->paeetTecnologia)
        ->get('/solicitacoes/'.$this->alheia->id)
        ->assertForbidden();
});

it('não vaza solicitações de outro eixo na listagem', function () {
    $this->actingAs($this->paeetTecnologia)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertDontSee('Contabilidade Geral');

    expect(SolicitacaoProva::query()->visivelPara($this->paeetTecnologia)->pluck('id')->all())
        ->toBe([$this->minha->id]);
});

it('um professor não alcança a solicitação de outro professor', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->minha))
        ->assertForbidden();

    expect($this->professor->can('responder', $this->minha))->toBeFalse()
        ->and($this->minha->professor->can('responder', $this->minha))->toBeTrue();
});

it('um professor não edita questão de outro professor', function () {
    $questao = $this->minha->questoes()->first();

    expect($this->professor->can('update', $questao))->toBeFalse()
        ->and($this->minha->professor->can('update', $questao))->toBeTrue();
});

it('nega encerrar solicitação de outro eixo', function () {
    expect($this->paeetTecnologia->can('encerrar', $this->alheia))->toBeFalse()
        ->and($this->paeetGestao->can('encerrar', $this->alheia))->toBeTrue();
});

it('o professor não vê o botão de nova solicitação', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertDontSee('Nova solicitação');
});
