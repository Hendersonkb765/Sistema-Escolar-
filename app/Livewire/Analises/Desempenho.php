<?php

namespace App\Livewire\Analises;

use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Enums\Bimestre;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use App\Support\GraficoDeEvolucao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Análise de desempenho: onde a turma teve dificuldade.
 *
 * Lê por habilidade e por questão. A primeira é a que permite intervir —
 * "interpretar estruturas de repetição" diz o que ensinar de novo, e
 * "questão 7" não diz nada.
 *
 * Todos os recortes são por bimestre, porque é assim que o ano letivo é
 * conduzido.
 */
class Desempenho extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'turma', except: '')]
    public string $filtroTurma = '';

    #[Url(as: 'bimestre', except: '')]
    public string $filtroBimestre = '';

    #[Url(as: 'prova', except: '')]
    public string $filtroProva = '';

    #[Url(as: 'disciplina', except: '')]
    public string $filtroDisciplina = '';

    /** A habilidade aberta para ver aluno a aluno. */
    #[Url(as: 'habilidade', except: '')]
    public string $habilidadeAberta = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ResultadoAluno::class);
    }

    /**
     * As provas que entram na análise: só as que já têm resultado
     * importado, dentro dos filtros escolhidos.
     *
     * @return Collection<int, Prova>
     */
    public function provasAnalisadas(): Collection
    {
        return $this->consultaDeProvas()
            ->when($this->filtroProva !== '', fn (Builder $q) => $q->whereKey($this->filtroProva))
            ->get();
    }

    protected function consultaDeProvas(): Builder
    {
        return Prova::query()
            ->visivelPara(auth()->user())
            ->whereHas('resultados')
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->when($this->filtroTurma !== '', fn (Builder $q) => $q->where('turma_id', $this->filtroTurma))
            ->when($this->filtroBimestre !== '', fn (Builder $q) => $q->where('bimestre', $this->filtroBimestre))
            ->orderByDesc('gerada_em');
    }

    public function verAlunos(string $habilidade): void
    {
        $this->habilidadeAberta = $this->habilidadeAberta === $habilidade ? '' : $habilidade;
    }

    /**
     * A prova do recorte, quando ele aponta para uma só.
     *
     * O acerto nominal depende disso: o número da questão só quer dizer
     * alguma coisa dentro da prova em que ela saiu.
     */
    public function provaDoRecorte(): ?Prova
    {
        $provas = $this->provasAnalisadas();

        return $provas->count() === 1 ? $provas->first() : null;
    }

    /** A turma do recorte, quando ele aponta para uma só. */
    public function turmaDoRecorte(): ?Turma
    {
        if ($this->filtroTurma !== '') {
            return Turma::query()->visivelPara(auth()->user())->find($this->filtroTurma);
        }

        $turmas = $this->provasAnalisadas()->pluck('turma_id')->unique();

        return $turmas->count() === 1
            ? Turma::query()->visivelPara(auth()->user())->find($turmas->first())
            : null;
    }

    public function render(AnalisarDesempenhoAction $analisar): View
    {
        $provas = $this->provasAnalisadas();
        $disciplina = $this->filtroDisciplina === '' ? null : (int) $this->filtroDisciplina;

        $disciplinas = $provas->isEmpty()
            ? collect()
            : ProvaQuestao::query()
                ->whereIn('prova_id', $provas->modelKeys())
                ->with('disciplina:id,nome')
                ->get()
                ->pluck('disciplina.nome', 'disciplina.id')
                ->unique()
                ->sort();

        // A evolução ignora o filtro de prova: comparar bimestres exige
        // olhar além do recorte de uma avaliação só.
        $paraEvolucao = $this->consultaDeProvas()->get();

        return view('analises.desempenho', [
            'provas' => $provas,
            'grafico' => GraficoDeEvolucao::de(
                array_values(Bimestre::opcoes()),
                $analisar->evolucaoPorBimestre($paraEvolucao, auth()->user(), $disciplina),
            ),
            'alunosDaHabilidade' => $this->habilidadeAberta === ''
                ? collect()
                : $analisar->porAluno($provas, auth()->user(), $this->habilidadeAberta, $disciplina),
            'turmaDoRecorte' => $this->turmaDoRecorte(),
            'provasDisponiveis' => $this->consultaDeProvas()->get(),
            'turmas' => Turma::query()->visivelPara(auth()->user())->orderBy('nome')->pluck('nome', 'id'),
            'bimestres' => Bimestre::opcoes(),
            'disciplinas' => $disciplinas,
            'porHabilidade' => $analisar->porHabilidade($provas, auth()->user(), $disciplina),
            'porQuestao' => $analisar->porQuestao($provas, auth()->user(), $disciplina),
            'provaDoRecorte' => $provaUnica = $this->provaDoRecorte(),
            'acertoNominal' => $provaUnica === null
                ? null
                : $analisar->acertoDeCadaAluno($provaUnica, auth()->user(), $disciplina),
            'limite' => AnalisarDesempenhoAction::LIMITE_DE_ATENCAO,
        ])->layout('components.layouts.app', [
            'titulo' => 'Análise de desempenho',
            'subtitulo' => 'Por habilidade avaliada, e não só pelo número da questão',
        ]);
    }
}
