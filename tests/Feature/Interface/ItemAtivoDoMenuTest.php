<?php

/*
 * Um item do menu por vez fica destacado.
 *
 * A regra antiga marcava pelo prefixo da rota: "documentos.*". Servia
 * enquanto cada seção tinha um item só — "Provas" continua destacado em
 * `/provas/criar`, que é o que se quer de um item-pai. Mas a seção
 * Documentos do aluno tem três itens irmãos com o mesmo prefixo, e os
 * três acendiam juntos: o menu deixava de dizer onde a pessoa está.
 */

use App\Models\Eixo;
use App\Support\Navegacao;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
});

/** Os itens do menu que estão destacados nesta rota. */
function itensAtivosEm(string $rota, array $parametros = []): array
{
    $ativos = [];

    test()->actingAs(test()->admin)->get(route($rota, $parametros))->assertOk();

    foreach (Navegacao::paraUsuario(test()->admin) as $grupo) {
        foreach ($grupo['itens'] as $item) {
            if ($item['ativo']) {
                $ativos[] = $item['rotulo'];
            }
        }
    }

    return $ativos;
}

it('destaca um item só em cada tela da seção de documentos', function (string $rota, string $esperado) {
    expect(itensAtivosEm($rota))->toBe([$esperado]);
})->with([
    ['documentos.index', 'Modelos de documento'],
    ['documentos.gerar', 'Gerar documentos'],
    ['documentos.compartilhados', 'Modelos compartilhados'],
]);

/*
 * A tela de criar não tem item próprio: quem fica destacado é a lista de
 * onde ela nasce. É o comportamento que o prefixo dava, e que a correção
 * não pode perder.
 */
it('mantém a lista destacada nas telas filhas dela', function () {
    expect(itensAtivosEm('documentos.criar'))->toBe(['Modelos de documento'])
        ->and(itensAtivosEm('modelos-prova.criar'))->toBe(['Modelos de prova'])
        ->and(itensAtivosEm('usuarios.criar'))->toBe(['Usuários'])
        ->and(itensAtivosEm('solicitacoes.criar'))->toBe(['Solicitações']);
});

it('destaca um item só em toda tela do sistema', function (string $rota) {
    expect(itensAtivosEm($rota))->toHaveCount(1);
})->with([
    'painel', 'eixos.index', 'cursos.index', 'disciplinas.index', 'grades.index',
    'turmas.index', 'alunos.index', 'solicitacoes.index', 'questoes.index',
    'provas.index', 'modelos-prova.index', 'importacoes.index', 'resultados.index',
    'analises.index', 'documentos.index', 'documentos.gerar', 'documentos.compartilhados',
    'usuarios.index', 'auditoria.index',
]);
