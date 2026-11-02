/*
 * O sino de avisos, no navegador: `npm run test:js`.
 *
 * O `fetch` e o `$wire` são trocados por dublês: o que se testa é quando
 * o sino chama o servidor, e quando deixa de chamar.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { criarSino } from '../../resources/js/sino-de-avisos.js';

/** Um sino com respostas em fila e o registro do que ele pediu ao Livewire. */
function sinoCom(respostas, versaoInicial = 0) {
    const pedidos = [];
    const chamadas = { verificar: 0, endereco: 0 };

    const buscar = async (url) => {
        pedidos.push(url);
        const proxima = respostas.shift();

        if (proxima instanceof Error) {
            throw proxima;
        }

        return {
            status: proxima.status ?? 200,
            ok: (proxima.status ?? 200) < 400,
            json: async () => proxima.corpo,
        };
    };

    const sino = criarSino('/sinal/velho', versaoInicial, buscar);
    sino.$wire = {
        verificar: () => { chamadas.verificar++; },
        enderecoDoSinal: async () => { chamadas.endereco++; return '/sinal/novo'; },
    };

    return { sino, pedidos, chamadas };
}

test('chama o servidor só quando o contador sobe', async () => {
    const { sino, chamadas } = sinoCom([{ corpo: { versao: 0 } }, { corpo: { versao: 1 } }, { corpo: { versao: 1 } }]);

    await sino.consultar();
    await sino.consultar();
    await sino.consultar();

    assert.equal(chamadas.verificar, 1);
    assert.equal(sino.versao, 1);
});

test('adota o contador que desceu sem tratar como aviso: o cache foi limpo', async () => {
    const { sino, chamadas } = sinoCom([{ corpo: { versao: 0 } }, { corpo: { versao: 1 } }], 5);

    await sino.consultar();
    assert.equal(chamadas.verificar, 0);
    assert.equal(sino.versao, 0);

    // E o próximo aviso, depois do recomeço, é percebido.
    await sino.consultar();
    assert.equal(chamadas.verificar, 1);
});

test('com 403, pede um endereço novo uma vez e segue com ele', async () => {
    const { sino, pedidos, chamadas } = sinoCom([{ status: 403 }, { corpo: { versao: 1 } }]);

    await sino.consultar();
    await sino.consultar();

    assert.equal(chamadas.endereco, 1);
    assert.deepEqual(pedidos, ['/sinal/velho', '/sinal/novo']);
    assert.equal(chamadas.verificar, 1);
});

test('para de perguntar quando o endereço novo também é recusado', async () => {
    const { sino, pedidos, chamadas } = sinoCom([{ status: 403 }, { status: 403 }, { corpo: { versao: 9 } }]);

    await sino.consultar();
    await sino.consultar();
    await sino.consultar();

    assert.equal(chamadas.endereco, 1);
    assert.equal(sino.parado, true);
    assert.equal(pedidos.length, 2);
    assert.equal(chamadas.verificar, 0);
});

test('renova de novo num 403 futuro, depois de ter voltado a responder', async () => {
    const { sino, chamadas } = sinoCom([{ status: 403 }, { corpo: { versao: 0 } }, { status: 403 }, { corpo: { versao: 0 } }]);

    for (let i = 0; i < 4; i++) {
        await sino.consultar();
    }

    assert.equal(chamadas.endereco, 2);
    assert.equal(sino.parado, false);
});

test('ignora rede fora e resposta sem contador, sem chamar o servidor', async () => {
    const { sino, chamadas } = sinoCom([new Error('offline'), { status: 500 }, { corpo: { outra: 'coisa' } }]);

    await sino.consultar();
    await sino.consultar();
    await sino.consultar();

    assert.equal(chamadas.verificar, 0);
    assert.equal(chamadas.endereco, 0);
    assert.equal(sino.parado, false);
});
