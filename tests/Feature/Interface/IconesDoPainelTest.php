<?php

/*
 * Cada card do painel tem um ícone, o mesmo do item do menu daquele
 * assunto: o olho acha "Provas sem resultado" pelo desenho antes de ler.
 *
 * Chave de ícone desconhecida não quebra nada — cai no ícone do painel,
 * em silêncio. Por isso o teste confere que nenhum card ficou com ele.
 */

use App\Livewire\Painel;
use App\Models\Eixo;
use App\Models\ProfessorDisciplina;
use App\Support\Navegacao;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    ProfessorDisciplina::query()->create([
        'usuario_id' => $this->professor->id, 'disciplina_id' => $montagem['disciplinas']['Lógica']->id,
        'turma_id' => null, 'ativo' => true,
    ]);

    $this->padrao = Navegacao::icone('painel');
});

it('desenha um ícone em cada indicador', function (string $perfil) {
    $componente = Livewire::actingAs($perfil === 'paeet' ? $this->paeet : $this->professor)->test(Painel::class);

    $indicadores = $componente->viewData('indicadores');

    expect(count($indicadores))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($componente->html(), 'data-icone-do-indicador'))->toBe(count($indicadores));

    foreach ($indicadores as $indicador) {
        expect($indicador['icone'])->toStartWith('<svg')
            ->and($indicador['icone'])->not->toBe($this->padrao, "\"{$indicador['rotulo']}\" ficou com o ícone padrão.");
    }
})->with(['paeet', 'professor']);

it('usa ícones diferentes para assuntos diferentes', function () {
    $porRotulo = collect(Livewire::actingAs($this->paeet)->test(Painel::class)->viewData('indicadores'))
        ->pluck('icone', 'rotulo');

    expect($porRotulo['Atrasadas'])->not->toBe($porRotulo['Solicitações aguardando envio'])
        ->and($porRotulo['Provas sem resultado'])->not->toBe($porRotulo['Importações a confirmar']);
});

it('desenha um ícone em cada item da estrutura do Eixo', function () {
    $componente = Livewire::actingAs($this->paeet)->test(Painel::class);
    $estrutura = $componente->viewData('estrutura');

    expect(count($estrutura))->toBeGreaterThanOrEqual(2);

    foreach ($estrutura as $item) {
        expect(Navegacao::icone($item['icone']))->not->toBe($this->padrao, "\"{$item['rotulo']}\" ficou com o ícone padrão.");
    }

    expect(substr_count($componente->html(), 'class="h-4 w-4 shrink-0"'))->toBe(count($estrutura));
});

it('põe ícone nos atalhos de quem dá aula', function () {
    $html = $this->actingAs($this->professor)->get(route('painel'))->assertOk()->getContent();

    // A escala da semana, Minha semana e Minhas ausências.
    expect(substr_count($html, 'data-icone-do-cartao'))->toBe(3);
});
