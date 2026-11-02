/**
 * O relógio da escala da semana, no navegador.
 *
 * A escala marca o dia de hoje e a aula em andamento. Antes ela pedia ao
 * servidor uma tela nova a cada minuto — uma conexão com o banco por
 * minuto, por usuário, e a hospedagem limita as conexões por hora. A hora
 * avança sozinha: basta o navegador saber o horário de cada aula e olhar
 * o relógio da escola (o fuso vem do servidor, na meta `fuso-da-escola`).
 *
 * A regra é a mesma de `QuadroHorario::acontecendoEm()`: o dia da aula, e
 * a hora entre o início (incluído) e o fim (excluído).
 */

/** A data "AAAA-MM-DD", o dia da semana (1 = segunda … 7 = domingo) e a hora "HH:MM" num fuso. */
export function momentoNoFuso(data, fuso) {
    const partes = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone: fuso, year: 'numeric', month: '2-digit', day: '2-digit',
            weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(data).map((p) => [p.type, p.value]),
    );

    const dias = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

    return {
        data: `${partes.year}-${partes.month}-${partes.day}`,
        dia: dias[partes.weekday],
        hora: `${partes.hour}:${partes.minute}`,
    };
}

/**
 * A tela foi desenhada noutro dia e precisa ser recarregada?
 *
 * Os marcadores andam sozinhos, mas o resto do painel é do dia em que a
 * página abriu: bimestre, próximos eventos, ausências. Quem deixa o painel
 * aberto de sexta a segunda veria segunda marcada sobre os dados de sexta.
 *
 * Uma vez por dia, no máximo: com o relógio do computador adiantado, o
 * servidor desenharia a tela de novo com a data antiga, e sem a trava a
 * página recarregaria sem parar.
 */
export function deveRecarregar(dataDaTela, dataAgora, jaRecarregadaPara) {
    return dataAgora > dataDaTela && jaRecarregadaPara !== dataAgora;
}

export function acontecendo(momento, dia, inicio, fim) {
    return momento.dia === dia && inicio <= momento.hora && momento.hora < fim;
}

/** Intervalo: qualquer dia de aula (domingo não tem). */
export function intervaloAcontecendo(momento, inicio, fim) {
    return momento.dia !== 7 && inicio <= momento.hora && momento.hora < fim;
}

export function registrarRelogio(Alpine) {
    const fuso = document.querySelector('meta[name="fuso-da-escola"]')?.content || 'America/Sao_Paulo';

    Alpine.store('relogio', {
        ...momentoNoFuso(new Date(), fuso),

        hoje(dia) {
            return this.dia === dia;
        },

        agora(dia, inicio, fim) {
            return acontecendo(this, dia, inicio, fim);
        },

        intervaloAgora(inicio, fim) {
            return intervaloAcontecendo(this, inicio, fim);
        },

        /** Chamado pela escala com a data em que o servidor a desenhou. */
        recarregarSeOutroDia(dataDaTela) {
            const dataAgora = this.data;
            let jaRecarregada = null;

            try {
                jaRecarregada = sessionStorage.getItem('painel-recarregado-para');
            } catch {
                // Sem sessionStorage (aba privada restrita): ainda assim recarrega uma vez por carga.
            }

            if (! deveRecarregar(dataDaTela, dataAgora, jaRecarregada)) {
                return;
            }

            const recarregar = () => {
                try {
                    sessionStorage.setItem('painel-recarregado-para', dataAgora);
                } catch {
                    //
                }

                window.location.reload();
            };

            // Aba escondida: recarrega quando a pessoa voltar a ela, e não às 0h para ninguém ver.
            if (document.hidden) {
                document.addEventListener('visibilitychange', recarregar, { once: true });
            } else {
                recarregar();
            }
        },
    });

    // A cada 20 s basta: a marca muda de minuto em minuto.
    setInterval(() => Object.assign(Alpine.store('relogio'), momentoNoFuso(new Date(), fuso)), 20000);
}
