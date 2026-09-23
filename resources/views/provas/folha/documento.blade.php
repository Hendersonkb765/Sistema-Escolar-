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
        'fonte' => $modelo->layout['fonte'] ?? 'sans',
        'tamanho' => (int) ($modelo->layout['tamanho'] ?? 11),
    ])
</head>
<body>
    <div class="cabecalho">
        <table>
            <tr>
                @if ($logo)
                    <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="instituicao">{{ $modelo->instituicao ?: config('app.name') }}</div>
                    <div class="avaliacao">{{ $modelo->nome_avaliacao ?: 'Avaliação' }} — {{ $prova->titulo }}</div>
                    <div class="meta">
                        {{ $prova->turma->curso->nome }} · Turma {{ $prova->turma->nome }} ·
                        {{ $prova->turma->periodo }}º período
                        @if ($prova->data_aplicacao)
                            · {{ $prova->data_aplicacao->format('d/m/Y') }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        @if ($modelo->cabecalho)
            <div class="meta">{{ $modelo->cabecalho }}</div>
        @endif
    </div>

    @php $campos = $modelo->campos_identificacao ?: ['aluno', 'matricula', 'turma', 'data']; @endphp

    @if ($campos !== [])
        <div class="identificacao">
            @if (in_array('aluno', $campos, true))
                <div class="linha"><span class="rotulo">Aluno(a):</span> <span class="preencher">&nbsp;</span></div>
            @endif
            <div class="linha">
                @if (in_array('matricula', $campos, true))
                    <span class="rotulo">Matrícula:</span> <span class="preencher" style="min-width: 25%">&nbsp;</span>
                @endif
                @if (in_array('turma', $campos, true))
                    <span class="rotulo">Turma:</span> {{ $prova->turma->nome }}
                @endif
                @if (in_array('curso', $campos, true))
                    <span class="rotulo">Curso:</span> {{ $prova->turma->curso->nome }}
                @endif
                @if (in_array('data', $campos, true))
                    <span class="rotulo">Data:</span> ___/___/______
                @endif
            </div>
            @if (in_array('nota', $campos, true) || in_array('assinatura', $campos, true))
                <div class="linha">
                    @if (in_array('nota', $campos, true))
                        <span class="rotulo">Nota:</span> <span class="preencher" style="min-width: 20%">&nbsp;</span>
                    @endif
                    @if (in_array('assinatura', $campos, true))
                        <span class="rotulo">Assinatura do professor:</span> <span class="preencher">&nbsp;</span>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($prova->instrucoes)
        <div class="instrucoes">{{ $prova->instrucoes }}</div>
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
