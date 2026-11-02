/*
 * O relógio da escala, que roda no navegador: `npm run test:js`.
 *
 * A regra tem de ser a mesma de QuadroHorario::acontecendoEm(), no
 * servidor — é ele quem desenha a tela, e o navegador só a mantém em dia.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { acontecendo, deveRecarregar, intervaloAcontecendo, momentoNoFuso } from '../../resources/js/relogio-da-escola.js';

const fuso = 'America/Sao_Paulo';

test('lê data, dia e hora no fuso da escola, e não no do computador', () => {
    // 10:20 em UTC é 07:20 de terça em Brasília.
    assert.deepEqual(momentoNoFuso(new Date('2026-05-12T10:20:00Z'), fuso), { data: '2026-05-12', dia: 2, hora: '07:20' });
    // 02:30 de segunda em UTC ainda é domingo, 23:30, em Brasília.
    assert.deepEqual(momentoNoFuso(new Date('2026-05-18T02:30:00Z'), fuso), { data: '2026-05-17', dia: 7, hora: '23:30' });
});

test('recarrega o painel quando o dia vira, uma vez por dia', () => {
    // Aberto na sexta, ainda sexta: nada.
    assert.equal(deveRecarregar('2026-05-15', '2026-05-15', null), false);
    // Aberto na sexta, já é segunda: recarrega.
    assert.equal(deveRecarregar('2026-05-15', '2026-05-18', null), true);
    // Já recarregou para segunda e o servidor desenhou sexta de novo (relógio do computador adiantado): para.
    assert.equal(deveRecarregar('2026-05-15', '2026-05-18', '2026-05-18'), false);
    // O recarregamento de outro dia não trava o de hoje.
    assert.equal(deveRecarregar('2026-05-18', '2026-05-19', '2026-05-18'), true);
    // Relógio do computador atrasado: não recarrega para trás.
    assert.equal(deveRecarregar('2026-05-18', '2026-05-17', null), false);
});

test('marca a aula do dia entre o início, incluído, e o fim, excluído', () => {
    assert.equal(acontecendo({ dia: 2, hora: '07:00' }, 2, '07:00', '07:50'), true);
    assert.equal(acontecendo({ dia: 2, hora: '07:49' }, 2, '07:00', '07:50'), true);
    assert.equal(acontecendo({ dia: 2, hora: '07:50' }, 2, '07:00', '07:50'), false);
    assert.equal(acontecendo({ dia: 3, hora: '07:20' }, 2, '07:00', '07:50'), false);
});

test('marca o intervalo em qualquer dia de aula, e não no domingo', () => {
    assert.equal(intervaloAcontecendo({ dia: 1, hora: '17:10' }, '17:00', '17:20'), true);
    assert.equal(intervaloAcontecendo({ dia: 6, hora: '17:10' }, '17:00', '17:20'), true);
    assert.equal(intervaloAcontecendo({ dia: 7, hora: '17:10' }, '17:00', '17:20'), false);
});
