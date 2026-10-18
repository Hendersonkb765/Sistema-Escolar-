<?php

namespace App\Livewire\Escopo;

use App\Models\Curso;
use App\Models\EscopoAula;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection as ColecaoSimples;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * O escopo-sequência: o que se ensina em cada semana do curso.
 *
 * A tela é de consulta, e serve a dois usos diferentes. O professor
 * chega para saber o que dar na semana que vem — e abre já nos
 * componentes dele. A coordenação chega para ver o percurso inteiro, ou
 * para conferir se o que foi pedido numa prova está no escopo.
 *
 * Por isso a semana é a unidade da tela: é assim que o documento é
 * escrito e é assim que a aula é planejada.
 */
class VisaoDoEscopo extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    #[Url(as: 'periodo', except: '')]
    public string $filtroPeriodo = '';

    #[Url(as: 'bimestre', except: '')]
    public string $filtroBimestre = '';

    #[Url(as: 'componente', except: '')]
    public string $filtroComponente = '';

    #[Url(as: 'busca', except: '')]
    public string $busca = '';

    /**
     * Abrir só nos componentes de quem olha.
     *
     * Liga sozinho para o professor — é o que ele veio ver —, e fica
     * desligado para a gestão. Em qualquer dos dois dá para trocar: a
     * semana do colega não é segredo, só não é o que se procurava.
     */
    #[Url(as: 'meus', except: false)]
    public bool $soOsMeus = false;

    /**
     * Só as aulas de recomposição da aprendizagem.
     *
     * No escopo real elas vêm em semanas inteiras, uma por bimestre —
     * e "quando é a semana de recomposição" é pergunta que se faz ao
     * planejar o bimestre, não rolando a página atrás de um selo.
     */
    #[Url(as: 'recomposicao', except: false)]
    public bool $soRecomposicao = false;

    /** A semana aberta, para ver as aulas dela. */
    public ?string $semanaAberta = null;

    public function mount(): void
    {
        $this->authorize('viewAny', EscopoAula::class);

        if (! auth()->user()->ehGestao() && auth()->user()->disciplinaIds() !== []) {
            $this->soOsMeus = true;
        }

        $this->escolherCursoSozinho();
    }

    /**
     * Com um curso só à vista, escolhê-lo é o que a pessoa faria.
     *
     * Abrir numa tela vazia pedindo que ela selecione a única opção
     * existente é trabalho que o sistema pode poupar.
     */
    protected function escolherCursoSozinho(): void
    {
        if ($this->filtroCurso !== '') {
            return;
        }

        $cursos = $this->cursosDisponiveis();

        if ($cursos->count() === 1) {
            $this->filtroCurso = (string) $cursos->first()->id;
        }
    }

    public function updatedFiltroCurso(): void
    {
        $this->reset(['filtroPeriodo', 'filtroBimestre', 'filtroComponente', 'semanaAberta']);
    }

    public function updatedFiltroPeriodo(): void
    {
        $this->reset(['filtroBimestre', 'semanaAberta']);
    }

    public function abrirSemana(string $chave): void
    {
        $this->semanaAberta = $this->semanaAberta === $chave ? null : $chave;
    }

    /** Os cursos que já têm escopo importado, dentro do escopo de quem olha. */
    public function cursosDisponiveis(): Collection
    {
        return Curso::query()
            ->visivelPara(auth()->user())
            ->whereHas('escopoAulas', fn (Builder $q) => $q->visivelPara(auth()->user()))
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'duracao_anos', 'eixo_id']);
    }

    /** A consulta base, já recortada por quem olha e pelo curso escolhido. */
    protected function consulta(): Builder
    {
        return EscopoAula::query()
            ->visivelPara(auth()->user())
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroPeriodo !== '', fn (Builder $q) => $q->where('periodo', $this->filtroPeriodo));
    }

    /** @return ColecaoSimples<int, int> */
    public function periodosDisponiveis(): ColecaoSimples
    {
        if ($this->filtroCurso === '') {
            return collect();
        }

        return EscopoAula::query()
            ->visivelPara(auth()->user())
            ->where('curso_id', $this->filtroCurso)
            ->distinct()
            ->orderBy('periodo')
            ->pluck('periodo');
    }

    /** Os componentes do recorte, para o select. @return ColecaoSimples<string, string> */
    public function componentesDisponiveis(): ColecaoSimples
    {
        return $this->consulta()
            ->get(['componente_nome', 'componente_codigo'])
            ->mapWithKeys(fn (EscopoAula $aula) => [$aula->componente_nome => $aula->componente()])
            ->sort();
    }

    /** @return ColecaoSimples<int, int> */
    public function bimestresDisponiveis(): ColecaoSimples
    {
        return $this->consulta()->distinct()->orderBy('bimestre')->pluck('bimestre');
    }

    /**
     * As aulas do recorte, agrupadas por bimestre e semana.
     *
     * @return array<int, array{bimestre: int, semanas: array<int, array<string, mixed>>}>
     */
    public function porSemana(): array
    {
        if ($this->filtroCurso === '') {
            return [];
        }

        $termo = trim($this->busca);
        $minhas = auth()->user()->disciplinaIds();

        $aulas = $this->consulta()
            ->when($this->filtroBimestre !== '', fn (Builder $q) => $q->where('bimestre', $this->filtroBimestre))
            ->when($this->filtroComponente !== '', fn (Builder $q) => $q->where('componente_nome', $this->filtroComponente))
            ->when($this->soOsMeus && $minhas !== [], fn (Builder $q) => $q->whereIn('disciplina_id', $minhas))
            ->when($this->soRecomposicao, fn (Builder $q) => $q->deRecomposicao())
            /*
             * A busca cobre o que se procura de verdade: o título da
             * aula, o tema da semana e o objeto de conhecimento. Quem
             * procura "função" quer a aula, não o componente inteiro.
             */
            ->when($termo !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->where('titulo_aula', 'like', "%{$termo}%")
                ->orWhere('tema_semana', 'like', "%{$termo}%")
                ->orWhere('objeto_conhecimento', 'like', "%{$termo}%")
                ->orWhere('habilidades_tecnicas', 'like', "%{$termo}%")))
            ->with('disciplina:id,nome')
            ->orderBy('bimestre')
            ->orderBy('semana')
            ->orderBy('ordem')
            ->get();

        return $aulas
            ->groupBy('bimestre')
            ->map(fn (Collection $doBimestre, int $bimestre) => [
                'bimestre' => $bimestre,
                'semanas' => $doBimestre
                    ->groupBy('semana')
                    ->map(fn (Collection $daSemana, int $semana) => [
                        'semana' => $semana,
                        'chave' => $bimestre.'-'.$semana,
                        // Quantas da semana são de recomposição: no escopo
                        // real a semana inteira costuma ser, e saber disso
                        // sem abrir é o que a lista precisa dizer.
                        'recomposicao' => $daSemana->filter->ehRecomposicao()->count(),
                        /*
                         * "Tema da semana" é da semana DAQUELE componente:
                         * na semana 1 do escopo real há seis temas, um por
                         * componente. Mostrar o primeiro como se fosse o da
                         * semana põe "Introdução a Aplicações Móveis" em
                         * cima de uma semana que também tem banco de dados
                         * e inteligência artificial. Com mais de um, a
                         * linha cala — e cada aula diz o seu.
                         */
                        'tema' => $daSemana->pluck('tema_semana')->filter()->unique()->count() === 1
                            ? $daSemana->pluck('tema_semana')->filter()->first()
                            : null,
                        'temasPorComponente' => $daSemana
                            ->filter(fn (EscopoAula $aula) => $aula->tema_semana !== null)
                            ->groupBy(fn (EscopoAula $aula) => $aula->componente())
                            ->map(fn (Collection $aulas) => $aulas->first()->tema_semana)
                            ->sortKeys()
                            ->all(),
                        'componentes' => $daSemana->map->componente()->unique()->sort()->values()->all(),
                        'aulas' => $daSemana->values(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        $porSemana = $this->porSemana();

        return view('escopo.visao', [
            'cursos' => $this->cursosDisponiveis(),
            'periodos' => $this->periodosDisponiveis(),
            'bimestres' => $this->bimestresDisponiveis(),
            'componentes' => $this->componentesDisponiveis(),
            'porSemana' => $porSemana,
            'totalDeAulas' => collect($porSemana)
                ->flatMap(fn (array $b) => $b['semanas'])
                ->sum(fn (array $s) => $s['aulas']->count()),
            'temDisciplinas' => auth()->user()->disciplinaIds() !== [],
            // Só oferece o filtro quando há o que filtrar: planilha sem
            // a coluna preenchida não ganha uma caixa que não faz nada.
            'temRecomposicao' => $this->filtroCurso !== '' && (clone $this->consulta())->deRecomposicao()->exists(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Escopo-sequência',
            'subtitulo' => 'O que se ensina em cada semana do curso',
        ]);
    }
}
