/**
 * O sino de avisos, no navegador.
 *
 * Pergunta a cada 30 s pelo contador de avisos num endereço que não abre o
 * banco (App\Support\SinalDeAvisos), e só chama o servidor — `verificar`,
 * que busca os avisos novos — quando o contador sobe.
 *
 * Dois cuidados:
 *
 * - 403 é a assinatura que deixou de valer (chave da aplicação trocada,
 *   endereço que chega diferente pelo proxy). O sino pede um endereço novo
 *   ao servidor, uma vez; se ainda assim for recusado, para, em vez de
 *   seguir perguntando em silêncio para sempre.
 * - O contador só sobe. Se descer — o cache em arquivo foi limpo —, o sino
 *   adota o valor novo sem chamar o servidor: não é aviso, é recomeço.
 *
 * `buscar` é o fetch, trocável nos testes.
 */
export function criarSino(url, versao, buscar = (...argumentos) => fetch(...argumentos)) {
    return {
        aberto: false,
        url,
        versao,
        timer: null,
        renovado: false,
        parado: false,

        init() {
            this.timer = setInterval(() => this.consultar(), 30000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        async consultar() {
            if (this.parado || (typeof document !== 'undefined' && document.hidden)) {
                return;
            }

            let resposta;

            try {
                resposta = await buscar(this.url, { cache: 'no-store', headers: { Accept: 'application/json' } });
            } catch {
                return; // Sem rede agora; tenta de novo na próxima volta.
            }

            if (resposta.status === 403) {
                await this.renovarEndereco();

                return;
            }

            if (! resposta.ok) {
                return;
            }

            let dados;

            try {
                dados = await resposta.json();
            } catch {
                return;
            }

            if (! Number.isInteger(dados?.versao)) {
                return;
            }

            // Respondeu: um 403 futuro merece outra renovação.
            this.renovado = false;

            const subiu = dados.versao > this.versao;
            this.versao = dados.versao;

            if (subiu) {
                this.$wire.verificar();
            }
        },

        async renovarEndereco() {
            if (this.renovado) {
                this.parado = true;

                return;
            }

            this.renovado = true;

            try {
                this.url = await this.$wire.enderecoDoSinal();
            } catch {
                this.parado = true;
            }
        },
    };
}
