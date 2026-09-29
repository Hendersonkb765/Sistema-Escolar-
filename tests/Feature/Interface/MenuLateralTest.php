<?php

/*
 * A barra lateral pode ser recolhida para o conteúdo ganhar a largura
 * dela — a pré-visualização da prova é o caso que mais pede isso.
 *
 * O que se confere aqui é a marcação, não o CSS: um `:class` perdido num
 * refactor não quebra teste nenhum de comportamento, e a tela volta a
 * ficar estreita sem ninguém notar.
 */

use App\Models\Eixo;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);

    $this->html = fn () => $this->actingAs($this->admin)->get(route('painel'))->getContent();
});

it('oferece o botão de esconder o menu', function () {
    expect(($this->html)())
        ->toContain('menuRecolhido = ! menuRecolhido')
        ->toContain('Esconder o menu lateral');
});

it('lembra a escolha entre visitas', function () {
    // `$persist` guarda em `localStorage`, e é a mesma chave que o véu
    // do `<head>` lê antes da primeira pintura.
    expect(($this->html)())
        ->toContain("\$persist(false).as('menu-recolhido')")
        ->toContain("localStorage.getItem('menu-recolhido')");
});

it('devolve ao conteúdo a largura da barra', function () {
    $html = ($this->html)();

    // A barra só aparece no desktop quando não está recolhida, e o
    // recuo do conteúdo acompanha.
    expect($html)
        ->toContain("{ 'lg:flex': ! menuRecolhido }")
        ->toContain("{ 'lg:pl-64': ! menuRecolhido }");
});

it('tira o véu assim que o Alpine assume', function () {
    // Sem isto, a regra `!important` do `<head>` brigaria com as classes
    // do Alpine e o botão pararia de funcionar.
    expect(($this->html)())
        ->toContain('alpine:initialized')
        ->toContain("classList.remove('menu-recolhido')");
});

it('mantém o menu do celular, que é sobreposto e não recolhível', function () {
    $html = ($this->html)();

    expect($html)
        ->toContain('menuAberto = true')
        ->toContain('Abrir menu');
});
