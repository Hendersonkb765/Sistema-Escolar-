<?php

return [

    /*
    |--------------------------------------------------------------------------
    | A escola
    |--------------------------------------------------------------------------
    |
    | Nome da instituição que aparece nos documentos — folha de prova e
    | boletim. É diferente de `app.name`, que nomeia o **sistema**: um é
    | o software, o outro é a escola que o usa, e confundir os dois faz a
    | prova sair assinada pelo programa.
    |
    | Cada modelo de prova pode dizer outro nome, e cada prova pode dizer
    | outro ainda; isto é o que vale quando ninguém disse nada.
    |
    */

    'nome' => env('INSTITUICAO_NOME', 'E. E. Prof. Francisco Pereira de Souza Filho'),

    /*
    |--------------------------------------------------------------------------
    | Aulas por dia
    |--------------------------------------------------------------------------
    |
    | Quantas aulas o dia da escola tem. A escala da semana mostra da 1ª
    | até esta, com as vagas em branco: é o que deixa ver em que altura
    | do dia cada aula cai. Uma aula cadastrada além disto aparece mesmo
    | assim — o número aqui é o mínimo da grade, não um limite.
    |
    | Como o nome e os intervalos, é valor inicial: o PAEET Admin altera
    | em "Configuração da escola" (App\Support\ConfiguracaoDaEscola).
    |
    */

    'aulas_por_dia' => (int) env('INSTITUICAO_AULAS_POR_DIA', 7),

    /*
    |--------------------------------------------------------------------------
    | Fuso horário da escola
    |--------------------------------------------------------------------------
    |
    | O relógio da parede: é por ele que a escala diz qual aula está
    | acontecendo agora. O sistema grava em UTC (app.timezone), e é
    | preciso dizer em que fuso a escola vive.
    |
    */

    'fuso' => env('APP_TIMEZONE', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Intervalos do dia
    |--------------------------------------------------------------------------
    |
    | Este é o valor inicial: o PAEET Admin altera em "Configuração da
    | escola", e o que ele grava vale por cima deste.
    |
    | Horário de início e duração em minutos. `alcance` diz a quem se
    | aplica: `todas` (o padrão), `exceto` as turmas listadas, ou
    | `somente` as listadas — é como uma turma com horário diferente
    | ganha o intervalo dela.
    |
    */

    'intervalos' => [
        ['nome' => 'Intervalo', 'inicio' => '17:00', 'minutos' => 20, 'alcance' => 'todas', 'turmas' => []],
        ['nome' => 'Intervalo', 'inicio' => '19:00', 'minutos' => 50, 'alcance' => 'todas', 'turmas' => []],
    ],

];
