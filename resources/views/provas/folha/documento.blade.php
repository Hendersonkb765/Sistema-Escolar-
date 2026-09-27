{{--
    A folha de prova. É a mesma view para a pré-visualização na tela e
    para o PDF — o que o professor confere é exatamente o que imprime.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $prova->titulo }}</title>
    @include('provas.folha.estilo', [
        'colunas' => $prova->colunas(),
        'layout' => $layout,
        'paraImpressao' => $paraImpressao,
        'tamanhoDaInstituicao' => $prova->tamanhoDaInstituicao(),
    ])
</head>
<body>
    @unless ($paraImpressao)
        {{-- Numeração da tela. No PDF quem a desenha é o cabeçalho do mPDF. --}}
        <div class="paginacao"></div>
    @endunless

    <div class="cabecalho">
        {{--
            Uma logo em cada extremo, com o texto ao centro. As células
            dos extremos existem mesmo sem imagem: é o que mantém a
            identificação centrada quando só uma das logos foi enviada.

            A altura da logo vai no `style` do próprio `img`: o mPDF não
            aplica `max-height` de folha de estilo e desenharia a imagem
            no tamanho original.
        --}}
        <table class="marca">
            <tr>
                <td class="logo esquerda">
                    @if ($logos['esquerda'])
                        <img src="{{ $logos['esquerda'] }}" alt="" style="height: 18mm;">
                    @endif
                </td>
                <td class="titulo">
                    <div class="instituicao">{{ $prova->instituicaoDaFolha() }}</div>
                    <div class="avaliacao">{{ $prova->nomeDaAvaliacao() }} — {{ $prova->titulo }}</div>
                    <div class="meta">
                        {{ $prova->turma->curso->nome }} · Turma {{ $prova->turma->nome }} ·
                        {{ $prova->turma->periodo }}º período · {{ $prova->bimestre->rotulo() }}
                        @if ($prova->data_aplicacao)
                            · {{ $prova->data_aplicacao->format('d/m/Y') }}
                        @endif
                    </div>
                    @if ($modelo->cabecalho)
                        <div class="meta">{{ $modelo->cabecalho }}</div>
                    @endif
                </td>
                <td class="logo direita">
                    @if ($logos['direita'])
                        <img src="{{ $logos['direita'] }}" alt="" style="height: 18mm;">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @php $campos = $modelo->campos_identificacao ?: ['aluno', 'matricula', 'turma', 'data']; @endphp

    @if ($campos !== [])
        {{--
            Quadro de identificação em tabela: a linha para preencher é a
            borda inferior de uma célula. Um `span` com `display:
            inline-block` funcionaria no navegador e sumiria no PDF.
        --}}
        <table class="identificacao">
            @if (in_array('aluno', $campos, true))
                <tr>
                    <td class="rotulo">Aluno(a):</td>
                    <td class="risco" colspan="3">&nbsp;</td>
                </tr>
            @endif

            @if (in_array('matricula', $campos, true) || in_array('turma', $campos, true))
                <tr>
                    @if (in_array('matricula', $campos, true))
                        <td class="rotulo">Matrícula:</td>
                        <td class="risco">&nbsp;</td>
                    @endif
                    @if (in_array('turma', $campos, true))
                        <td class="rotulo">Turma:</td>
                        <td class="valor">{{ $prova->turma->nome }}</td>
                    @endif
                </tr>
            @endif

            @if (in_array('curso', $campos, true) || in_array('data', $campos, true))
                <tr>
                    @if (in_array('curso', $campos, true))
                        <td class="rotulo">Curso:</td>
                        <td class="valor">{{ $prova->turma->curso->nome }}</td>
                    @endif
                    @if (in_array('data', $campos, true))
                        <td class="rotulo">Data:</td>
                        <td class="valor">___/___/______</td>
                    @endif
                </tr>
            @endif

            @if (in_array('nota', $campos, true) || in_array('assinatura', $campos, true))
                <tr>
                    @if (in_array('nota', $campos, true))
                        <td class="rotulo">Nota:</td>
                        <td class="risco">&nbsp;</td>
                    @endif
                    @if (in_array('assinatura', $campos, true))
                        <td class="rotulo">Assinatura:</td>
                        <td class="risco">&nbsp;</td>
                    @endif
                </tr>
            @endif
        </table>
    @endif

    @if ($prova->instrucoes)
        <div class="instrucoes">{{ $prova->instrucoes }}</div>
    @endif

    {{--
        O `column-count` do CSS resolve na tela. O mPDF não o entende:
        para ele o fluxo em colunas se abre com a tag `<columns>` e se
        fecha voltando para uma coluna só.
    --}}
    @if ($paraImpressao && $prova->colunas() > 1)
        <columns column-count="{{ $prova->colunas() }}" column-gap="10"/>
    @endif

    <div class="corpo">
        @foreach ($questoesPorDisciplina as $disciplina => $questoes)
            <div class="disciplina">{{ $disciplina }} — questões {{ $questoes->min('numero') }} a {{ $questoes->max('numero') }}</div>

            @foreach ($questoes as $questao)
                @include('provas.folha.questao', [
                    'questao' => $questao,
                    'comGabarito' => $comGabarito,
                    'mostrarPesos' => $prova->mostrarPesos(),
                    'origemDaImagem' => $origemDaImagem,
                ])
            @endforeach
        @endforeach
    </div>

    @if ($paraImpressao && $prova->colunas() > 1)
        <columns column-count="1"/>
    @endif

    @if ($comGabarito)
        <div class="gabarito">
            <strong>Gabarito</strong>
            <table>
                <tr>
                    @foreach ($prova->gabarito() as $numero => $letra)
                        <td>{{ $numero }}</td>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($prova->gabarito() as $letra)
                        <td><strong>{{ $letra }}</strong></td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    @if ($modelo->rodape)
        <div class="rodape">{{ $modelo->rodape }}</div>
    @endif
</body>
</html>
