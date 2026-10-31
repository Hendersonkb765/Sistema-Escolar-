<?php

/*
 * Redefinir a senha do aluno é feito na SED, não aqui. O sistema só
 * guarda o código que a SED usa para identificar o aluno e abre a página
 * dela já apontada para ele.
 *
 * A página da SED exige que o professor esteja logado nela — por isso o
 * link abre no mesmo navegador, em outra aba, e não por redirecionamento
 * do servidor: quem leva a sessão da SED é o navegador.
 *
 * O professor, que antes não abria a tela de alunos, passa a abri-la
 * para consulta: só os alunos das turmas em que dá aula, sem criar nem
 * editar ninguém.
 */

use App\Livewire\Alunos\FormularioAluno;
use App\Livewire\Alunos\ListaAlunos;
use App\Models\Aluno;
use App\Models\Eixo;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves', 'ra' => '5001', 'codigo_reset' => '11122233']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '5002', 'codigo_reset' => '44455566']);
    $this->semCodigo = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Lia Moraes', 'ra' => '5003', 'codigo_reset' => null]);

    solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->professor, 'questoes' => 2],
    ]);

    // Mesmo Eixo, outro curso, sem o professor: ele não dá aula ali.
    $outro = cursoComGrade($this->eixo, [1 => ['Contabilidade']], autor: $this->paeet);
    $outraTurma = Turma::factory()->doCurso($outro['curso'], $outro['grade'])
        ->create(['periodo' => 1, 'nome' => '1 Z']);
    $this->alheio = Aluno::factory()->naTurma($outraTurma)
        ->create(['nome' => 'Otávio Quintela', 'ra' => '9001', 'codigo_reset' => '99988877']);
});

it('monta o endereço da SED com o código do aluno', function () {
    expect($this->marina->linkDeResetDeSenhaNaSed())
        ->toBe('https://sed.educacao.sp.gov.br/MinhasTurmas/ResetSenhaAluno?codigoAluno=11122233');
});

it('não monta endereço para aluno sem código', function (?string $codigo) {
    $aluno = Aluno::factory()->make(['codigo_reset' => $codigo]);

    expect($aluno->linkDeResetDeSenhaNaSed())->toBeNull();
})->with([null, '', '   ']);

it('escapa o código na URL', function () {
    $aluno = Aluno::factory()->make(['codigo_reset' => '12 34&x=1']);

    expect($aluno->linkDeResetDeSenhaNaSed())
        ->toBe('https://sed.educacao.sp.gov.br/MinhasTurmas/ResetSenhaAluno?codigoAluno=12+34%26x%3D1');
});

it('mostra ao professor os alunos das turmas dele, cada um com o link da SED', function () {
    $this->actingAs($this->professor)
        ->get(route('alunos.index'))
        ->assertOk()
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        ->assertSee($this->marina->linkDeResetDeSenhaNaSed(), false)
        ->assertSee($this->caio->linkDeResetDeSenhaNaSed(), false)
        ->assertDontSee('Otávio Quintela')
        ->assertDontSee('99988877');
});

it('abre a SED em outra aba do mesmo navegador, e não por navegação do Livewire', function () {
    $html = Livewire::actingAs($this->professor)->test(ListaAlunos::class)->html();

    preg_match_all('/<a [^>]*codigoAluno=[^>]*>/', $html, $links);

    expect($links[0])->toHaveCount(2);

    foreach ($links[0] as $link) {
        expect($link)->toContain('target="_blank"')
            // Vindo de outro site, a SED manda para a página inicial.
            ->and($link)->toContain('noreferrer')
            ->and($link)->toContain('referrerpolicy="no-referrer"')
            ->and($link)->not->toContain('wire:navigate');
    }
});

it('diz por que o aluno sem código não tem o botão', function () {
    $html = Livewire::actingAs($this->professor)->test(ListaAlunos::class)->html();

    expect($html)->toContain('Sem código da SED')
        ->and(substr_count($html, 'Redefinir senha na SED'))->toBe(2);
});

it('não deixa o professor matricular nem editar aluno', function () {
    $this->actingAs($this->professor)->get(route('alunos.criar'))->assertForbidden();
    $this->actingAs($this->professor)->get(route('alunos.editar', $this->marina))->assertForbidden();

    $this->actingAs($this->professor)->get(route('alunos.index'))
        ->assertDontSee('Novo aluno')
        ->assertDontSee(route('alunos.editar', $this->marina), false)
        // A turma aparece pelo nome, sem link para uma tela que daria 403.
        ->assertSee('1 A')
        ->assertDontSee(route('turmas.show', $this->turma), false);
});

it('mostra a lista vazia, e não 403, ao professor sem turma', function () {
    $this->actingAs(professor($this->eixo))
        ->get(route('alunos.index'))
        ->assertOk()
        ->assertSee('Nenhum aluno encontrado')
        ->assertDontSee('Marina Alves');
});

it('nega o reset ao professor que não dá aula na turma do aluno', function () {
    expect($this->professor->can('redefinirSenhaNaSed', $this->marina))->toBeTrue()
        ->and($this->professor->can('redefinirSenhaNaSed', $this->alheio))->toBeFalse();
});

it('nega o reset e esconde os alunos do PAEET de outro Eixo', function () {
    $deFora = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect($deFora->can('redefinirSenhaNaSed', $this->marina))->toBeFalse();

    $this->actingAs($deFora)->get(route('alunos.index'))
        ->assertOk()
        ->assertDontSee('Marina Alves')
        ->assertDontSee('11122233');

    $this->actingAs($deFora)->get(route('alunos.editar', $this->marina))->assertForbidden();
});

it('deixa a coordenação usar o link na mesma lista', function () {
    $this->actingAs($this->paeet)
        ->get(route('alunos.index'))
        ->assertSee($this->marina->linkDeResetDeSenhaNaSed(), false)
        ->assertSee($this->alheio->linkDeResetDeSenhaNaSed(), false);
});

it('grava o código pelo formulário do aluno, como veio', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class, ['aluno' => $this->semCodigo])
        ->assertSet('codigo_reset', '')
        ->set('codigo_reset', ' 0007788 ')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->semCodigo->fresh()->codigo_reset)->toBe('0007788');
});

it('apaga o código quando o campo fica em branco', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class, ['aluno' => $this->marina])
        ->assertSet('codigo_reset', '11122233')
        ->set('codigo_reset', '')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->marina->fresh()->codigo_reset)->toBeNull();
});
