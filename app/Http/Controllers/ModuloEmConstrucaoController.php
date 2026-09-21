<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Disciplina;
use App\Models\GradeCurricular;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ponto de entrada dos módulos cuja interface chega nos próximos
 * milestones. A rota já existe e já autoriza: um professor que tente
 * alcançar a criação de uma solicitação, de uma prova ou de uma
 * importação recebe 403 hoje, não quando a tela ficar pronta.
 *
 * Cada milestone substitui a entrada correspondente por seu componente.
 */
class ModuloEmConstrucaoController extends Controller
{
    /**
     * @var array<string, array{model: class-string, habilidade: string, titulo: string, descricao: string, etapa: string}>
     */
    protected array $modulos = [
        'disciplinas.index' => [
            'model' => Disciplina::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Disciplinas',
            'descricao' => 'Cadastro de disciplinas independentes de ano, reaproveitáveis entre cursos.',
            'etapa' => 'Milestone 2',
        ],
        'grades.index' => [
            'model' => GradeCurricular::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Grades curriculares',
            'descricao' => 'Grades versionadas por curso: alterar a grade cria uma nova versão e preserva as anteriores.',
            'etapa' => 'Milestone 2',
        ],
        'turmas.index' => [
            'model' => Turma::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Turmas',
            'descricao' => 'Turmas com a versão da grade congelada, avanço de ano e histórico completo.',
            'etapa' => 'Milestone 2',
        ],
        'alunos.index' => [
            'model' => Aluno::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Alunos',
            'descricao' => 'Matrículas por turma, com histórico de movimentações.',
            'etapa' => 'Milestone 2',
        ],
        'solicitacoes.index' => [
            'model' => SolicitacaoProva::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Solicitações de questões',
            'descricao' => 'Pedidos de questões aos professores, com pesos por item e controle de prazo.',
            'etapa' => 'Milestone 3',
        ],
        'solicitacoes.criar' => [
            'model' => SolicitacaoProva::class,
            'habilidade' => 'create',
            'titulo' => 'Nova solicitação',
            'descricao' => 'Criação de solicitações de questões — exclusiva de PAEET e PAEET Admin.',
            'etapa' => 'Milestone 3',
        ],
        'questoes.index' => [
            'model' => Questao::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Questões',
            'descricao' => 'Preenchimento pelo professor, análise pela gestão, feedbacks e reenvio versionado.',
            'etapa' => 'Milestones 3 e 4',
        ],
        'provas.index' => [
            'model' => Prova::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Provas',
            'descricao' => 'Montagem automática a partir das questões aprovadas, com snapshot imutável e PDF.',
            'etapa' => 'Milestone 5',
        ],
        'provas.criar' => [
            'model' => Prova::class,
            'habilidade' => 'create',
            'titulo' => 'Nova prova',
            'descricao' => 'Montagem de prova — exclusiva de PAEET e PAEET Admin.',
            'etapa' => 'Milestone 5',
        ],
        'modelos-prova.index' => [
            'model' => ModeloProva::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Modelos de prova',
            'descricao' => 'Cabeçalho institucional, logo, campos de identificação, layout e rodapé.',
            'etapa' => 'Milestone 5',
        ],
        'importacoes.index' => [
            'model' => Importacao::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Importação de resultados',
            'descricao' => 'Planilha modelo, validação em dry-run com relatório linha a linha e confirmação transacional.',
            'etapa' => 'Milestone 6',
        ],
        'importacoes.criar' => [
            'model' => Importacao::class,
            'habilidade' => 'create',
            'titulo' => 'Nova importação',
            'descricao' => 'Envio de planilha de resultados — exclusivo de PAEET e PAEET Admin.',
            'etapa' => 'Milestone 6',
        ],
        'resultados.index' => [
            'model' => ResultadoAluno::class,
            'habilidade' => 'viewAny',
            'titulo' => 'Resultados e notas',
            'descricao' => 'Nota por disciplina calculada pelos pesos, com detalhamento questão a questão.',
            'etapa' => 'Milestone 7',
        ],
    ];

    public function __invoke(Request $request): View
    {
        $rota = (string) $request->route()->getName();

        abort_unless(isset($this->modulos[$rota]), 404);

        $modulo = $this->modulos[$rota];

        $this->authorize($modulo['habilidade'], $modulo['model']);

        return view('em-breve.index', [
            'titulo' => $modulo['titulo'],
            'subtitulo' => 'Em construção',
            'descricao' => $modulo['descricao'],
            'etapa' => $modulo['etapa'],
        ]);
    }
}
