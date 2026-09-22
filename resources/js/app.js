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
