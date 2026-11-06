<?php

namespace App\Livewire\Horarios;

use App\Enums\DiaDaSemana;
use App\Enums\StatusTurma;
use App\Livewire\Attributes\LembrarFiltro;
use App\Livewire\Concerns\LembraFiltros;
use App\Models\QuadroHorario;
use App\Support\IntervaloDoDia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * A semana no painel, com o dia de hoje marcado.
 *
 * A coordenação vê o quadro das turmas ativas de **todos** os Eixos — o
 * PAEET precisa saber quem está em sala na escola inteira, para cobrir
 * uma falta com quem está livre —, os do seu Eixo primeiro. É uma
 * exceção deliberada ao escopo por Eixo, e só de consulta: a escala não
 * leva a tela nenhuma de outro Eixo, e turma, quadro e alunos seguem
 * restritos. Os filtros por Eixo e curso existem porque a escola tem
 * muitas turmas.
 *
 * O professor vê as próprias aulas, numa linha só.
 *
 * O PAEET que também dá aula vê a escala do Eixo: a dele está em
 * "Minha semana".
 */
class EscalaDaSemana extends Component
{
    use LembraFiltros;

    /** @var array<int, string> número da aula => primeiro início dela na escola, "HH:MM". Só para montar a grade. */
    private array $horarioDaEscola = [];

    #[LembrarFiltro]
    public string $eixo = '';

    #[LembrarFiltro]
    public string $curso = '';

    /** Trocar de Eixo invalida o curso escolhido no anterior. */
    public function updatedEixo(): void
    {
        $this->curso = '';
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $daCoordenacao = $usuario->ehGestao();

        $meusEixos = $daCoordenacao ? $usuario->eixoIds() : [];

        $aulas = QuadroHorario::query()
            // A coordenação, sem `visivelPara`: a escala é da escola inteira (ver a nota da classe).
            ->when(! $daCoordenacao, fn (Builder $q) => $q->where('professor_id', $usuario->id))
            ->whereHas('turma', fn (Builder $q) => $q->where('status', StatusTurma::Ativa))
            ->with('turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id', 'turma.curso.eixo:id,nome', 'disciplina:id,nome', 'professor:id,nome')
            ->orderBy('aula_numero')
            ->get();

        // Os filtros vêm do quadro inteiro, antes de filtrar: escolher um não some com os outros.
        $eixos = $aulas->pluck('turma.curso.eixo')->unique('id')
            ->sortBy(fn ($eixo) => [in_array($eixo->id, $meusEixos, true) ? 0 : 1, $eixo->nome])
            ->values();
        $cursos = $aulas->pluck('turma.curso')
            ->filter(fn ($curso) => $this->eixo === '' || (string) $curso->eixo_id === $this->eixo)
            ->unique('id')->sortBy('nome')->values();

        if ($daCoordenacao) {
            $aulas = $aulas
                ->filter(fn (QuadroHorario $a) => $this->eixo === '' || (string) $a->turma->curso->eixo_id === $this->eixo)
                ->filter(fn (QuadroHorario $a) => $this->curso === '' || (string) $a->turma->curso_id === $this->curso)
                ->values();
        }

        // Pelo relógio da escola, e não pelo do servidor (UTC): é a hora da parede que diz qual aula é agora.
        $momento = now(config('instituicao.fuso'));
        $hoje = DiaDaSemana::daData($momento);

        // A que horas começa cada número de aula, pela escola inteira: é o que situa o
        // intervalo numa turma que não tem aula em todas as posições do dia.
        $this->horarioDaEscola = QuadroHorario::query()
            ->toBase()
            ->selectRaw('aula_numero, min(hora_inicio) as inicio')
            ->groupBy('aula_numero')
            ->pluck('inicio', 'aula_numero')
            ->map(fn ($inicio) => substr((string) $inicio, 0, 5))
            ->all();

        // Sábado só entra quando há aula nele, ou quando hoje é sábado.
        $dias = collect(DiaDaSemana::cases())
            ->reject(fn (DiaDaSemana $dia) => $dia === DiaDaSemana::Sabado
                && $hoje !== DiaDaSemana::Sabado
                && $aulas->every(fn (QuadroHorario $a) => $a->dia_semana !== DiaDaSemana::Sabado))
            ->values();

        return view('horarios.escala-da-semana', [
            'daCoordenacao' => $daCoordenacao,
            'linhas' => $daCoordenacao ? $this->porTurma($aulas, $meusEixos) : $this->doProfessor($aulas),
            'eixos' => $eixos,
            'meusEixos' => $meusEixos,
            'cursos' => $cursos,
            'dias' => $dias,
            'hoje' => $hoje,
            'momento' => $momento,
        ]);
    }

    /**
     * Um bloco por turma, agrupados por Eixo e curso: os Eixos de quem
     * olha primeiro, depois os demais, em ordem de nome.
     *
     * @param  array<int, int>  $meusEixos
     * @return Collection<int, array{rotulo: string, grupo: ?string, eixo: ?string, doMeuEixo: bool, linhas: array, porCelula: Collection}>
     */
    private function porTurma(Collection $aulas, array $meusEixos): Collection
    {
        return $aulas
            ->groupBy('turma_id')
            ->map(function (Collection $daTurma) use ($meusEixos) {
                $turma = $daTurma->first()->turma;

                return [
                    ...$this->bloco($turma->nome, $turma->curso->nome, $daTurma, IntervaloDoDia::daTurma($turma)),
                    'eixo' => $turma->curso->eixo->nome,
                    'doMeuEixo' => in_array($turma->curso->eixo_id, $meusEixos, true),
                ];
            })
            ->sortBy([
                fn ($a, $b) => $b['doMeuEixo'] <=> $a['doMeuEixo'],
                ['eixo', 'asc'],
                ['grupo', 'asc'],
                ['rotulo', 'asc'],
            ])
            ->values();
    }

    /** @return Collection<int, array{rotulo: string, grupo: ?string, linhas: array, porCelula: Collection}> */
    private function doProfessor(Collection $aulas): Collection
    {
        // A linha única mistura turmas: entra o intervalo que vale para todas elas.
        $turmas = $aulas->pluck('turma_id')->unique();
        $comuns = IntervaloDoDia::padrao()->filter(fn (IntervaloDoDia $i) => $turmas->every(fn (int $id) => $i->aplicaA($id)));

        return $aulas->isEmpty() ? collect() : collect([[...$this->bloco('Suas aulas', null, $aulas, $comuns), 'eixo' => null, 'doMeuEixo' => true]]);
    }

    /**
     * Uma linha por número de aula, da 1ª até a última do dia da escola
     * (`instituicao.aulas_por_dia`), com as vagas em branco. Quem só dá a
     * 3ª aula vê a 1ª e a 2ª vazias acima dela, e a grade continua dizendo
     * em que altura do dia cada aula cai. Uma aula cadastrada além disso
     * estende a grade em vez de sumir.
     *
     * A célula é uma lista: o professor pode ter a 1ª aula da manhã numa
     * turma e a 1ª da tarde em outra.
     *
     * @param  Collection<int, IntervaloDoDia>  $intervalos
     * @return array{rotulo: string, grupo: ?string, linhas: array<int, array{aula?: int, intervalo?: IntervaloDoDia}>, porCelula: Collection}
     */
    private function bloco(string $rotulo, ?string $grupo, Collection $aulas, Collection $intervalos): array
    {
        $ultima = max((int) config('instituicao.aulas_por_dia'), (int) $aulas->max('aula_numero'));
        $depoisDa = $this->posicionar($aulas, $intervalos, $ultima);
        $linhas = [];

        // Intervalo antes da 1ª aula, se houver.
        foreach ($depoisDa->get(0, []) as $intervalo) {
            $linhas[] = ['intervalo' => $intervalo];
        }

        foreach (range(1, $ultima) as $numero) {
            $linhas[] = ['aula' => $numero];

            foreach ($depoisDa->get($numero, []) as $intervalo) {
                $linhas[] = ['intervalo' => $intervalo];
            }
        }

        return [
            'rotulo' => $rotulo,
            'grupo' => $grupo,
            'linhas' => $linhas,
            'porCelula' => $aulas->sortBy('hora_inicio')
                ->groupBy(fn (QuadroHorario $a) => $a->dia_semana->value.'-'.$a->aula_numero),
        ];
    }

    /**
     * Em que altura da grade cada intervalo entra.
     *
     * O intervalo é um horário ("às 17h"), e a grade é por número de
     * aula. Ele entra depois da última aula que começa antes dele. A hora
     * de cada número vem das aulas do próprio bloco e, onde o bloco não
     * tem aula, do resto da escola — a 3ª aula começa no mesmo horário em
     * todas as turmas, ainda que esta não tenha 3ª aula nenhuma.
     *
     * Sem aula nenhuma antes dele, o intervalo abre o dia (posição 0).
     *
     * @param  Collection<int, IntervaloDoDia>  $intervalos
     * @return Collection<int, array<int, IntervaloDoDia>> número da aula => intervalos depois dela
     */
    private function posicionar(Collection $aulas, Collection $intervalos, int $ultima): Collection
    {
        $inicios = $this->horarioDaEscola;

        foreach ($aulas->groupBy('aula_numero') as $numero => $daPosicao) {
            $inicios[$numero] = $daPosicao->map(fn (QuadroHorario $a) => $a->horaInicio())->min();
        }

        $posicoes = [];

        foreach ($intervalos as $intervalo) {
            $antes = collect($inicios)
                ->filter(fn (string $inicio, int $numero) => $inicio < $intervalo->inicio && $numero <= $ultima)
                ->keys()
                ->max() ?? 0;

            $posicoes[$antes][] = $intervalo;
        }

        return collect($posicoes);
    }
}
