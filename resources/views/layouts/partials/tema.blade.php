{{--
    Aplica o tema antes da primeira pintura.

    Sem isto, a página nasce clara e escurece quando o JavaScript roda —
    um lampejo branco a cada recarga para quem escolheu o escuro. Roda
    no `<head>`, síncrono de propósito: adiar seria o mesmo que não
    fazer.

    Fica em `window` porque três lugares precisam da mesma regra: aqui,
    o seletor no Alpine e o `livewire:navigated`. O `wire:navigate`
    troca os atributos do `<html>` pelos do documento novo, que vem do
    servidor sem a classe — sem reaplicar, o tema se perde na primeira
    navegação.

    Três escolhas: `claro`, `escuro` e `sistema` (o padrão), que segue a
    preferência do aparelho.
--}}
<script>
    window.aplicarTema = function () {
        var escolha = 'sistema';

        try {
            escolha = localStorage.getItem('tema') || 'sistema';
        } catch (erro) {
            // Janela anônima ou armazenamento bloqueado: vale o sistema.
        }

        var doSistema = window.matchMedia('(prefers-color-scheme: dark)').matches;

        document.documentElement.classList.toggle(
            'dark',
            escolha === 'escuro' || (escolha === 'sistema' && doSistema),
        );
    };

    window.aplicarTema();

    /*
     * Logo após a troca de página, e não no `DOMContentLoaded`: numa
     * navegação do Livewire o documento não recarrega.
     */
    document.addEventListener('livewire:navigated', window.aplicarTema);
</script>
