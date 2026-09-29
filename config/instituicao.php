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

];
