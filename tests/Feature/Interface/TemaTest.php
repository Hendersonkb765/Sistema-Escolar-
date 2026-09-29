<?php

/*
 * O tema é escolha do usuário — claro, escuro ou o do aparelho — e não
 * a preferência do sistema imposta pelo CSS.
 *
 * O que se confere aqui é a marcação: se o script do `<head>` sumir num
 * refactor, a página nasce clara e escurece quando o JavaScript roda,
 * e ninguém vê isso num teste de comportamento.
 */

use App\Models\Eixo;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
});

it('aplica o tema antes da primeira pintura', function (string $html) {
    // Sem isto, um lampejo branco a cada recarga para quem escolheu o
    // escuro. O script é síncrono de propósito.
    expect($html)
        ->toContain("localStorage.getItem('tema')")
        ->toContain('classList.toggle(')
        ->toContain("'(prefers-color-scheme: dark)'");
})->with([
    'painel' => fn () => test()->actingAs(test()->admin)->get(route('painel'))->getContent(),
    'login' => fn () => test()->get(route('login'))->getContent(),
]);

it('oferece as três escolhas onde o usuário as alcança', function (string $html) {
    expect($html)
        ->toContain('seletorDeTema')
        ->toContain("escolher('claro')")
        ->toContain("escolher('escuro')")
        ->toContain("escolher('sistema')");
})->with([
    'painel' => fn () => test()->actingAs(test()->admin)->get(route('painel'))->getContent(),
    // Também na entrada: a escolha vale antes de haver conta.
    'login' => fn () => test()->get(route('login'))->getContent(),
]);

it('marca a escolha com símbolo, e não só com cor', function () {
    $html = $this->actingAs($this->admin)->get(route('painel'))->getContent();

    expect($html)->toContain('aria-checked')
        ->toContain('✓');
});

it('liga o tema pela classe, e não pela preferência do sistema', function () {
    // Com `darkMode: 'media'` não haveria o que escolher: o CSS
    // seguiria o aparelho e o seletor não mudaria nada.
    $config = file_get_contents(base_path('tailwind.config.js'));

    expect($config)->toContain("darkMode: 'selector'");

    // E o que o projeto escreve à mão acompanha a mesma classe.
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('.dark ::-webkit-scrollbar-thumb')
        ->not->toContain('prefers-color-scheme');

    expect(file_get_contents(resource_path('views/components/grafico-evolucao.blade.php')))
        ->toContain('.dark .serie')
        ->not->toContain('prefers-color-scheme');
});
