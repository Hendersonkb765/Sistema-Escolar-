<?php

/*
 * O filtro que a pessoa escolhe numa tela continua lá quando ela volta,
 * até fechar o navegador.
 *
 * Fica num cookie de sessão — sem prazo, que o navegador apaga ao fechar —,
 * um por tela e por usuário. O filtro no endereço vence o guardado, e o
 * que é foco do momento (a aba, a questão aberta) não é guardado.
 */

use App\Enums\StatusTurma;
use App\Livewire\Analises\Desempenho;
use App\Livewire\Horarios\EscalaDaSemana;
use App\Livewire\Turmas\ListaTurmas;
use App\Models\Eixo;
use App\Models\Turma;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create(['periodo' => 1, 'nome' => 'Turma Ativa Alfa']);
    Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => 'Turma Arquivada Beta', 'status' => StatusTurma::Arquivada]);

    $this->cookie = fn ($usuario = null) => 'filtros-listaturmas-'.($usuario ?? $this->paeet)->id;
});

it('guarda o filtro escolhido num cookie que dura até o navegador fechar', function () {
    Livewire::actingAs($this->paeet)->test(ListaTurmas::class)->set('filtroStatus', 'arquivada');

    $guardado = Cookie::queued(($this->cookie)());

    expect($guardado)->not->toBeNull()
        // Prazo zero: cookie de sessão, que o navegador apaga ao fechar.
        ->and($guardado->getExpiresTime())->toBe(0)
        ->and(json_decode($guardado->getValue(), true))->toMatchArray(['filtroStatus' => 'arquivada', 'busca' => '']);
});

it('reabre a tela com o filtro guardado, já marcado no seletor', function () {
    $html = $this->actingAs($this->paeet)
        ->withCookie(($this->cookie)(), json_encode(['filtroStatus' => 'arquivada']))
        ->get(route('turmas.index'))
        ->assertOk()
        ->assertSee('Turma Arquivada Beta')
        ->assertDontSee('Turma Ativa Alfa')
        ->getContent();

    expect($html)->toMatch('/<option value="arquivada"\s+selected/');
});

it('deixa o filtro do endereço vencer o guardado', function () {
    $this->actingAs($this->paeet)
        ->withCookie(($this->cookie)(), json_encode(['filtroStatus' => 'arquivada']))
        ->get(route('turmas.index', ['situacao' => 'ativa']))
        ->assertSee('Turma Ativa Alfa')
        ->assertDontSee('Turma Arquivada Beta');
});

it('não passa o filtro de um usuário para outro no mesmo navegador', function () {
    $outro = paeet($this->eixo);

    $this->actingAs($outro)
        ->withCookie(($this->cookie)($this->paeet), json_encode(['filtroStatus' => 'arquivada']))
        ->get(route('turmas.index'))
        ->assertSee('Turma Ativa Alfa')
        ->assertSee('Turma Arquivada Beta');
});

it('guarda filtros, e não o foco do momento', function () {
    Livewire::actingAs($this->paeet)->test(Desempenho::class)->set('filtroBimestre', '2')->set('aba', 'questoes');

    $guardado = json_decode(Cookie::queued('filtros-desempenho-'.$this->paeet->id)->getValue(), true);

    expect($guardado)->toHaveKey('filtroBimestre', '2')
        ->and($guardado)->not->toHaveKey('aba')
        ->and($guardado)->not->toHaveKey('questaoAberta');
});

it('lembra o filtro da escala da semana, que não vai no endereço', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);

    $this->actingAs($this->paeet)
        ->withCookie('filtros-escaladasemana-'.$this->paeet->id, json_encode(['eixo' => (string) $outroEixo->id, 'curso' => '']))
        ->get(route('painel'))
        ->assertOk();

    Livewire::actingAs($this->paeet)->test(EscalaDaSemana::class)->set('eixo', (string) $outroEixo->id);

    expect(json_decode(Cookie::queued('filtros-escaladasemana-'.$this->paeet->id)->getValue(), true))
        ->toBe(['eixo' => (string) $outroEixo->id, 'curso' => '']);
});

it('ignora um valor guardado que não serve mais, sem quebrar a tela', function () {
    $this->actingAs($this->paeet)
        ->withCookie(($this->cookie)(), json_encode(['porPagina' => 'muitas', 'filtroStatus' => 'arquivada']))
        ->get(route('turmas.index'))
        ->assertOk()
        // O que serve continua valendo.
        ->assertSee('Turma Arquivada Beta')
        ->assertDontSee('Turma Ativa Alfa');
});
