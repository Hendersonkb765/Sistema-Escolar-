{{--
    Aplica o tema antes da primeira pintura.

    Sem isto, a página nasce clara e escurece quando o JavaScript roda —
    um lampejo branco a cada recarga para quem escolheu o escuro. Roda
    no `<head>`, síncrono de propósito: adiar seria o mesmo que não
    fazer.

    Três escolhas: `claro`, `escuro` e `sistema` (o padrão), que segue a
    preferência do aparelho.
--}}
<script>
    (function () {
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
    })();
</script>
