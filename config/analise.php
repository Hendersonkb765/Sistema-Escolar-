<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Critérios da análise de desempenho
    |--------------------------------------------------------------------------
    |
    | Nenhum destes números é uma verdade sobre aprendizagem. São os
    | limites que ESTA escola adotou para ler os resultados, e estão aqui
    | — e não cravados no código — porque a escola pode mudá-los sem que
    | isso seja uma alteração de sistema.
    |
    | A tela diz qual valor usou em cada leitura, para quem olha o número
    | saber contra o que ele foi medido.
    |
    */

    /*
     * Aproveitamento (em %) a partir do qual uma habilidade é lida como
     * satisfatória. Abaixo disso ela aparece como indício de
     * dificuldade — indício, não diagnóstico.
     */
    'criterio_esperado' => (float) env('ANALISE_CRITERIO_ESPERADO', 60),

    /*
     * Nota (0 a 10) a partir da qual o aluno é contado como tendo
     * atingido o esperado na disciplina.
     */
    'nota_esperada' => (float) env('ANALISE_NOTA_ESPERADA', 6),

    /*
     * Evidência mínima para concluir alguma coisa sobre uma habilidade.
     *
     * Uma questão só, respondida por três alunos, não sustenta "a turma
     * não domina isto" — sustenta "precisa de mais evidência", que é uma
     * conclusão diferente e honesta.
     */
    'evidencia_minima' => [
        'questoes' => (int) env('ANALISE_MINIMO_QUESTOES', 2),
        'respostas' => (int) env('ANALISE_MINIMO_RESPOSTAS', 10),
    ],

    /*
     * Diferença (em pontos percentuais) a partir da qual a variação
     * entre dois períodos é lida como mudança, e não como oscilação.
     */
    'variacao_relevante' => (float) env('ANALISE_VARIACAO_RELEVANTE', 10),

    /*
     * Faixas de nota do painel da turma. A média sozinha esconde o grupo
     * que vai mal; a distribuição é que o mostra.
     */
    'faixas' => [
        ['ate' => 2.0, 'rotulo' => '0 a 2', 'cor' => 'vermelho'],
        ['ate' => 4.0, 'rotulo' => '2 a 4', 'cor' => 'vermelho'],
        ['ate' => 6.0, 'rotulo' => '4 a 6', 'cor' => 'amarelo'],
        ['ate' => 8.0, 'rotulo' => '6 a 8', 'cor' => 'verde'],
        ['ate' => 10.0, 'rotulo' => '8 a 10', 'cor' => 'verde'],
    ],

];
