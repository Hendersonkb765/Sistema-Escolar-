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
 * A formatação é guardada como marca no próprio texto (`**negrito**`,
 * `*itálico*`): quem a interpreta é o PHP, num lugar só
 * (`App\Support\TextoDoEnunciado`), e que serve à tela, ao PDF e ao
 * Word. Aqui só se envolve a seleção — nenhuma regra é duplicada.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('marcasDeTexto', () => ({
        envolver(marca) {
            const campo = this.$refs.campo;

            if (!campo || campo.disabled) {
                return;
            }

            const inicio = campo.selectionStart;
            const fim = campo.selectionEnd;
            const texto = campo.value;
            const selecao = texto.slice(inicio, fim);

            const jaMarcado =
                selecao.length > marca.length * 2 &&
                selecao.startsWith(marca) &&
                selecao.endsWith(marca);

            // Clicar de novo com o mesmo trecho selecionado desfaz.
            const trocado = jaMarcado
                ? selecao.slice(marca.length, -marca.length)
                : marca + selecao + marca;

            campo.value = texto.slice(0, inicio) + trocado + texto.slice(fim);

            // Sem seleção, o cursor fica entre as marcas, pronto para
            // digitar; com seleção, ela continua selecionada.
            const cursor = selecao === ''
                ? inicio + marca.length
                : inicio + trocado.length;

            campo.focus();
            campo.setSelectionRange(selecao === '' ? cursor : inicio, cursor);

            this.avisarLivewire(campo);
        },

        /**
         * Os campos usam `wire:model.blur`, que só sincroniza ao sair do
         * campo. Sem avisar, um clique em B seguido de "Salvar" perderia
         * a marca recém-inserida.
         */
        avisarLivewire(campo) {
            const atributo = [...campo.attributes]
                .map((a) => a.name)
                .find((nome) => nome.startsWith('wire:model'));

            if (atributo && this.$wire) {
                this.$wire.set(campo.getAttribute(atributo), campo.value, false);
            }

            campo.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));
});
