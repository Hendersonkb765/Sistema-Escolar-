<?php

namespace App\Actions\Academico;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EscopoAula;
use App\Models\User;
use App\Support\PlanilhaDeEscopo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Traz o escopo-sequência de um período para dentro do sistema.
 *
 * Em dois passos, como a importação de resultados: {@see conferir()}
 * não grava nada e devolve o que vai acontecer — quantas aulas entram,
 * quantas serão substituídas, quais códigos de componente não casaram
 * com disciplina nenhuma. Só depois de ver isso é que se confirma.
 *
 * Confirmar **substitui** o período inteiro. O escopo é um documento
 * que a rede publica por ano; mesclar linha a linha deixaria no banco
 * uma mistura de duas versões que ninguém saberia desfazer. Trocar o
 * período inteiro é uma operação que se entende e se repete.
 */
class ImportarEscopoAction
{
    /**
     * O que a importação faria, sem fazer.
     *
     * @return array{
     *     aulas: int, recusadas: array<int, array<string, mixed>>,
     *     substituira: int, colunas_ignoradas: array<int, string>,
     *     componentes: array<int, array{codigo: ?string, nome: string, aulas: int, disciplina: ?string}>,
     *     sem_disciplina: array<int, string>,
     * }
     */
    public function conferir(string $caminho, Curso $curso, int $periodo, User $autor): array
    {
        Gate::forUser($autor)->authorize('importar', EscopoAula::class);
        Gate::forUser($autor)->authorize('view', $curso);

        $this->validarPeriodo($curso, $periodo);

        $planilha = PlanilhaDeEscopo::ler($caminho);
        $aproveitaveis = $planilha->aproveitaveis();

        if ($aproveitaveis === []) {
            throw RegraDeNegocioException::porque(
                'Nenhuma linha da planilha pôde ser lida. Confira o bimestre, a semana, '
                .'o nome do componente e o título da aula.'
            );
        }

        $this->recusarNomesTrocados($planilha);

        $porDisciplina = $this->disciplinasPorCodigo($curso);

        $componentes = collect($aproveitaveis)
            ->groupBy(fn (array $linha) => $linha['componente_codigo'] ?? $linha['componente_nome'])
            ->map(fn (Collection $aulas) => [
                'codigo' => $aulas->first()['componente_codigo'],
                'nome' => $aulas->first()['componente_nome'],
                'aulas' => $aulas->count(),
                'disciplina' => $porDisciplina->get(
                    $this->chaveDoCodigo($aulas->first()['componente_codigo'])
                )?->nome,
            ])
            ->sortBy('nome')
            ->values()
            ->all();

        return [
            'aulas' => count($aproveitaveis),
            'recusadas' => $planilha->recusadas(),
            'substituira' => EscopoAula::query()
                ->where('curso_id', $curso->getKey())
                ->where('periodo', $periodo)
                ->count(),
            'colunas_ignoradas' => $planilha->colunasIgnoradas,
            'componentes' => $componentes,
            'sem_disciplina' => collect($componentes)
                ->where('disciplina', null)
                ->map(fn (array $c) => $c['nome'].($c['codigo'] ? ' ('.$c['codigo'].')' : ''))
                ->values()
                ->all(),
        ];
    }

    /**
     * Grava, substituindo o que havia naquele período.
     *
     * @return array{aulas: int, substituidas: int}
     */
    public function executar(string $caminho, Curso $curso, int $periodo, User $autor): array
    {
        $relatorio = $this->conferir($caminho, $curso, $periodo, $autor);

        $planilha = PlanilhaDeEscopo::ler($caminho);
        $porDisciplina = $this->disciplinasPorCodigo($curso);

        return DB::transaction(function () use ($curso, $periodo, $autor, $planilha, $porDisciplina, $relatorio) {
            $substituidas = EscopoAula::query()
                ->where('curso_id', $curso->getKey())
                ->where('periodo', $periodo)
                ->delete();

            foreach ($planilha->aproveitaveis() as $linha) {
                EscopoAula::query()->create([
                    'curso_id' => $curso->getKey(),
                    'periodo' => $periodo,
                    'disciplina_id' => $porDisciplina
                        ->get($this->chaveDoCodigo($linha['componente_codigo']))?->getKey(),
                    'bimestre' => $linha['bimestre'],
                    'semana' => $linha['semana'],
                    'ordem' => $linha['ordem'],
                    ...collect($linha)->only([
                        'componente_nome', 'componente_codigo', 'unidade_nome', 'unidade_codigo',
                        'titulo_aula', 'tema_semana', 'objetivos', 'objeto_conhecimento',
                        'habilidades_tecnicas', 'habilidades_socioemocionais',
                        'competencia_tecnica', 'competencias_socioemocionais',
                        'carga_horaria', 'aula_complementar', 'aulas_semanais',
                    ])->all(),
                ]);
            }

            activity('escopo')
                ->performedOn($curso)
                ->causedBy($autor)
                ->withProperties([
                    'periodo' => $periodo,
                    'aulas' => $relatorio['aulas'],
                    'substituidas' => $substituidas,
                    'componentes_sem_disciplina' => $relatorio['sem_disciplina'],
                ])
                ->log("Escopo-sequência do {$periodo}º período importado com {$relatorio['aulas']} aula(s)");

            return ['aulas' => $relatorio['aulas'], 'substituidas' => $substituidas];
        });
    }

    /**
     * Recusa a planilha em que um código traz vários nomes.
     *
     * Isso não é linha ruim solta: é coluna deslocada, e o que entraria
     * no banco seria o texto de outra coluna no lugar do nome da
     * matéria. Importar metade certa e metade embaralhada é pior que
     * não importar — o erro só apareceria na tela, dias depois, sem
     * nada que o ligasse ao arquivo.
     */
    protected function recusarNomesTrocados(PlanilhaDeEscopo $planilha): void
    {
        $trocados = $planilha->codigosComNomesTrocados();

        if ($trocados === []) {
            return;
        }

        $detalhe = collect($trocados)
            ->map(fn (array $nomes, string $codigo) => $codigo.' ('.count($nomes).' nomes: '
                .collect($this->exemplos($nomes))->map(fn (string $nome) => '"'.$nome.'"')->join(' e ')
                .')')
            ->join('; ');

        throw RegraDeNegocioException::porque(
            'Um código de componente identifica um componente só, e nesta planilha há código '
            .'com vários nomes: '.$detalhe.'. Quando isso acontece, as colunas costumam estar '
            .'deslocadas — confira se "Nome do componente" não está com o texto de outra coluna. '
            .'Corrija a planilha e envie de novo.'
        );
    }

    /**
     * Dois nomes que se distingam na tela.
     *
     * Cortados no mesmo ponto, dois parágrafos de competência saem
     * idênticos — "Tolerância ao estresse: Ter eficácia na…" duas
     * vezes, e quem lê a mensagem conclui que o sistema se enganou. O
     * segundo exemplo é o primeiro que fica diferente depois do corte.
     *
     * @param  array<int, string>  $nomes
     * @return array<int, string>
     */
    protected function exemplos(array $nomes): array
    {
        $limite = 60;
        $primeiro = Str::limit($nomes[0], $limite);

        foreach (array_slice($nomes, 1) as $nome) {
            if (Str::limit($nome, $limite) !== $primeiro) {
                return [$primeiro, Str::limit($nome, $limite)];
            }
        }

        // Todos começam igual: mostrar o mesmo texto duas vezes não diz nada.
        return [$primeiro];
    }

    protected function validarPeriodo(Curso $curso, int $periodo): void
    {
        if ($periodo < 1 || $periodo > $curso->duracao_anos) {
            throw RegraDeNegocioException::porque(
                "O curso {$curso->nome} tem {$curso->duracao_anos} período(s), e o escopo foi "
                ."enviado para o {$periodo}º."
            );
        }
    }

    /**
     * As disciplinas do curso, indexadas pelo código normalizado.
     *
     * Pelo código, e não pelo nome: "Programação Back-End" e
     * "Programação Back End" são a mesma disciplina para a escola e duas
     * para um `where`. O código é curto, estável e é o que a rede usa
     * para identificar o componente.
     *
     * @return Collection<string, Disciplina>
     */
    protected function disciplinasPorCodigo(Curso $curso): Collection
    {
        return $curso->disciplinas()
            ->get(['id', 'curso_id', 'nome', 'codigo'])
            ->keyBy(fn (Disciplina $disciplina) => $this->chaveDoCodigo($disciplina->codigo));
    }

    protected function chaveDoCodigo(?string $codigo): string
    {
        // Nulo vira uma chave que nenhuma disciplina tem: sem código não
        // há como casar, e casar "no escuro" seria pior que não casar.
        return $codigo === null ? "\0" : mb_strtoupper(trim($codigo));
    }
}
