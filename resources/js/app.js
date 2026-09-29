import './bootstrap';

import hljs from 'highlight.js/lib/common';
import kotlin from 'highlight.js/lib/languages/kotlin';
import swift from 'highlight.js/lib/languages/swift';
import 'highlight.js/styles/github-dark.css';

hljs.registerLanguage('kotlin', kotlin);
hljs.registerLanguage('swift', swift);

/**
 * Realça os blocos de código do enunciado. Roda no carregamento e depois
 * de cada atualização do Livewire, porque a tela de resposta troca os
 * blocos sem recarregar a página.
 */
const realcarCodigo = () => {
    document.querySelectorAll('pre code:not([data-realcado])').forEach((bloco) => {
        hljs.highlightElement(bloco);
        bloco.dataset.realcado = 'true';
    });
};

document.addEventListener('DOMContentLoaded', realcarCodigo);
document.addEventListener('livewire:navigated', realcarCodigo);
document.addEventListener('livewire:update', realcarCodigo);

/**
 * Negrito e itálico nos campos de enunciado.
 *
 * Aqui só se lê o que está selecionado e se devolve o cursor ao lugar.
 * Quem decide o que a marca faz é `App\Support\TextoDoEnunciado`, no
 * servidor — uma segunda cópia da regra em JavaScript é o caminho curto
 * para as duas divergirem.
 */
document.addEventListener('alpine:init', () => {
        window.Alpine.data('marcasDeTexto', () => ({
        init() {
            // O campo é redesenhado com o valor novo, e o cursor cairia no
            // fim. O servidor diz onde ele estava.
            window.addEventListener('marca-aplicada', (evento) => {
                const campo = this.$refs.campo;
                const { campo: caminho, inicio, fim } = evento.detail ?? {};

                if (!campo || caminho !== this.caminhoDoCampo(campo)) {
                    return;
                }

                requestAnimationFrame(() => {
                    campo.focus();
                    campo.setSelectionRange(inicio, fim);
                });
            });
        },

        envolver(marca) {
            const campo = this.$refs.campo;

            if (!campo || campo.disabled) {
                return;
            }

            const { value: texto, selectionStart: inicio, selectionEnd: fim } = campo;

            this.$wire.alternarMarca(
                this.caminhoDoCampo(campo),
                texto.slice(0, inicio),
                texto.slice(inicio, fim),
                texto.slice(fim),
                marca,
            );
        },

        /** A propriedade Livewire que o campo edita. */
        caminhoDoCampo(campo) {
            const atributo = [...campo.attributes]
                .map((a) => a.name)
                .find((nome) => nome.startsWith('wire:model'));

            return atributo ? campo.getAttribute(atributo) : null;
        },
    }));
});

/**
 * Escolha do tema: claro, escuro ou o do aparelho.
 *
 * O `<head>` já aplicou a classe antes da primeira pintura; aqui só se
 * troca a escolha, se guarda e se manda reaplicar. A regra de qual
 * classe vale é uma só, em `window.aplicarTema`.
 */
document.addEventListener('alpine:init', () => {
        window.Alpine.data('seletorDeTema', () => ({
        escolha: 'sistema',
        aberto: false,

        init() {
            try {
                this.escolha = localStorage.getItem('tema') || 'sistema';
            } catch (erro) {
                this.escolha = 'sistema';
            }

            // Em "sistema", acompanhar o aparelho sem recarregar a página.
            window
                .matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', () => this.aplicar());
        },

        escolher(valor) {
            this.escolha = valor;
            this.aberto = false;

            try {
                localStorage.setItem('tema', valor);
            } catch (erro) {
                // Sem guardar, a escolha vale só para esta visita.
            }

            this.aplicar();
        },

        // A regra de qual classe vale é a do `<head>`, que roda antes
        // de o Alpine existir e por isso não pode morar aqui.
        aplicar() {
            window.aplicarTema();
        },
    }));
});
