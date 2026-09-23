<?php

namespace App\Http\Controllers;

use App\Models\Importacao;
use App\Models\ResultadoAluno;
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
