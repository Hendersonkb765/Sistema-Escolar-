<?php

namespace App\Livewire\Configuracao;

use App\Actions\Configuracao\SalvarConfiguracaoDaEscolaAction;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Turma;
use App\Support\IntervaloDoDia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Os padrões da escola, num lugar só: o nome que sai nos documentos, as
 * aulas do dia e os intervalos que valem para todas as turmas. Só o
 * PAEET Admin abre — uma mudança aqui atinge a escola inteira.
 */
class ConfiguracaoDaEscola extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public string $nome = '';

    public string $aulas_por_dia = '';

    /**
     * As turmas vêm como texto: é o que o checkbox do navegador devolve.
     *
     * @var array<int, array{nome: string, inicio: string, fim: string, alcance: string, turmas: array<int, string>}>
     */
    public array $intervalos = [];

    public function mount(): void
    {
        $this->authorize('configurarEscola');

        $this->nome = (string) config('instituicao.nome');
        $this->aulas_por_dia = (string) config('instituicao.aulas_por_dia');
        $this->intervalos = IntervaloDoDia::padrao()
            ->map(fn (IntervaloDoDia $i) => [
                'nome' => $i->nome,
                'inicio' => $i->inicio,
                'fim' => $i->fim,
                'alcance' => $i->alcance,
                'turmas' => array_map('strval', $i->turmas),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'aulas_por_dia' => ['required', 'integer', 'between:1,12'],
            'intervalos.*.nome' => ['required', 'string', 'max:60'],
            'intervalos.*.inicio' => ['required', 'date_format:H:i'],
            'intervalos.*.fim' => ['required', 'date_format:H:i', 'after:intervalos.*.inicio'],
            'intervalos.*.alcance' => ['required', Rule::in([IntervaloDoDia::TODAS, IntervaloDoDia::EXCETO, IntervaloDoDia::SOMENTE])],
            'intervalos.*.turmas' => ['array'],
            'intervalos.*.turmas.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'nome' => 'nome da escola',
            'aulas_por_dia' => 'aulas por dia',
            'intervalos.*.nome' => 'nome',
            'intervalos.*.inicio' => 'início',
            'intervalos.*.fim' => 'fim',
        ];
    }

    public function adicionarIntervalo(): void
    {
        $this->intervalos[] = ['nome' => 'Intervalo', 'inicio' => '', 'fim' => '', 'alcance' => IntervaloDoDia::TODAS, 'turmas' => []];
    }

    public function removerIntervalo(int $indice): void
    {
        unset($this->intervalos[$indice]);
        $this->intervalos = array_values($this->intervalos);
    }

    public function salvar(SalvarConfiguracaoDaEscolaAction $action): void
    {
        $this->authorize('configurarEscola');

        $this->validate();

        try {
            $action->executar(auth()->user(), $this->nome, (int) $this->aulas_por_dia, array_map(
                fn (array $i) => [...$i, 'turmas' => array_map('intval', $i['turmas'] ?? [])],
                array_values($this->intervalos),
            ));
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso('Configuração da escola salva. Vale para todas as turmas que seguem o padrão.');
    }

    public function render(): View
    {
        return view('configuracao.escola', [
            // Todas as turmas ativas da escola, e não só as do Eixo de quem
            // olha: o intervalo é da escola inteira.
            'turmas' => Turma::query()
                ->where('status', StatusTurma::Ativa)
                ->with('curso:id,nome')
                ->get(['id', 'nome', 'curso_id'])
                ->sortBy([fn ($a, $b) => strcmp($a->curso->nome, $b->curso->nome), fn ($a, $b) => strcmp($a->nome, $b->nome)])
                ->values(),
            'alcances' => [
                IntervaloDoDia::TODAS => 'Todas as turmas',
                IntervaloDoDia::EXCETO => 'Todas, exceto as marcadas',
                IntervaloDoDia::SOMENTE => 'Só as turmas marcadas',
            ],
        ])->layout('components.layouts.app', [
            'titulo' => 'Configuração da escola',
            'subtitulo' => 'Os padrões que valem para todas as turmas e cursos',
        ]);
    }
}
