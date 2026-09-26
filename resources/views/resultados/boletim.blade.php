{{--
    Boletim da turma: uma página por aluno, para a escola entregar a
    folha certa a cada um. Cada página repete o cabeçalho, porque ela
    sai da pilha sozinha.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Boletim — {{ $turma->nome }}</title>
    <style>
        body {
            font-family: {!! $layout->familiaCss() !!};
            font-size: {{ $layout->tamanho }}pt;
            line-height: {{ $layout->espacamento }};
            color: #111;
        }
        .marca { width: 100%; border-collapse: collapse; }
        .marca .logo { width: 24mm; vertical-align: middle; }
        .marca .logo.direita { text-align: right; }
        .marca .titulo { text-align: center; vertical-align: middle; }
        .instituicao { font-size: {{ $layout->tamanho + 1 }}pt; font-weight: bold; }
        .documento { font-size: {{ $layout->tamanho }}pt; }
        .meta { font-size: {{ $layout->tamanhoSecundario() }}pt; color: #444; }
        .cabecalho { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }

        .aluno {
            width: 100%; border-collapse: collapse; border: 1px solid #111;
            margin-bottom: 10px; font-size: {{ $layout->tamanhoSecundario() }}pt;
        }
        .aluno td { padding: 4px 6px; }
        .aluno .rotulo { font-weight: bold; width: 26mm; }

        .notas { width: 100%; border-collapse: collapse; font-size: {{ $layout->tamanhoSecundario() }}pt; }
        .notas th {
            text-align: left; border-bottom: 1px solid #111; padding: 4px 6px;
            font-size: {{ max(7, $layout->tamanhoSecundario() - 1) }}pt; text-transform: uppercase;
        }
        .notas td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        .notas .numero { text-align: right; }
        .notas .abaixo { font-weight: bold; }

        .media { margin-top: 10px; border-top: 2px solid #111; padding-top: 6px; }
        .media .valor { font-size: {{ $layout->tamanho + 4 }}pt; font-weight: bold; }
        .rodape {
            margin-top: 14px; border-top: 1px solid #999; padding-top: 5px;
            font-size: {{ $layout->tamanhoSecundario() }}pt; color: #555;
        }
    </style>
</head>
<body>
@foreach ($alunos as $indice => $dados)
    @if ($indice > 0)
        <pagebreak/>
    @endif

    <div class="cabecalho">
        <table class="marca">
            <tr>
                <td class="logo esquerda">
                    @if ($logos['esquerda'])
                        <img src="{{ $logos['esquerda'] }}" alt="" style="height: 16mm;">
                    @endif
                </td>
                <td class="titulo">
                    <div class="instituicao">{{ $modelo?->instituicao ?: config('app.name') }}</div>
                    <div class="documento">
                        Boletim de notas{{ $bimestre ? ' — '.$bimestre->rotulo() : '' }}
                    </div>
                    <div class="meta">
                        {{ $turma->curso->nome }} · Turma {{ $turma->nome }} · {{ $turma->periodo }}º período
                    </div>
                </td>
                <td class="logo direita">
                    @if ($logos['direita'])
                        <img src="{{ $logos['direita'] }}" alt="" style="height: 16mm;">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="aluno">
        <tr>
            <td class="rotulo">Aluno(a):</td>
            <td>{{ $dados['aluno']->nome }}</td>
            <td class="rotulo">Matrícula:</td>
            <td>{{ $dados['aluno']->matricula }}</td>
        </tr>
    </table>

    <table class="notas">
        <thead>
            <tr>
                <th>Disciplina</th>
                <th>Bimestre</th>
                <th>Avaliação</th>
                <th class="numero">Pontos</th>
                <th class="numero">Nota</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados['linhas'] as $linha)
                <tr>
                    <td>{{ $linha['disciplina'] }}</td>
                    <td>{{ $linha['bimestre']->sigla() }}</td>
                    <td>{{ $linha['prova'] }}</td>
                    <td class="numero">
                        {{ number_format($linha['acertos'], 2, ',', '.') }}
                        de {{ number_format($linha['total'], 2, ',', '.') }}
                    </td>
                    <td @class(['numero', 'abaixo' => $linha['nota'] < 6])>
                        {{ number_format($linha['nota'], 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="media">
        Média das avaliações:
        <span class="valor">{{ number_format($dados['media'], 2, ',', '.') }}</span>
        <div class="meta">
            A nota de cada disciplina vale de 0 a 10 e é medida contra a soma dos
            pesos daquela disciplina — questões de disciplinas diferentes não se
            somam numa nota só.
        </div>
    </div>

    {{-- O rodapé do modelo é de prova ("Boa prova!") e não cabe aqui. --}}
    <div class="rodape">
        Emitido em {{ $emitidoEm->format('d/m/Y H:i') }} · {{ $turma->curso->nome }} · Turma {{ $turma->nome }}
    </div>
@endforeach
</body>
</html>
