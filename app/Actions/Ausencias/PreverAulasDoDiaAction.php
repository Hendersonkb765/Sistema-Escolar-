<?php

namespace App\Actions\Ausencias;

use App\Enums\DiaDaSemana;
use App\Enums\StatusTurma;
use App\Models\BimestreLetivo;
use App\Models\EscopoAula;
use App\Models\QuadroHorario;
use App\Models\User;
use App\Support\AulaPrevista;
use App\Support\AulasDoDia;
use App\Support\SemanaLetiva;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * As aulas que o professor daria numa data, cada uma com a aula do
 * escopo-sequência que caberia nela.
 *
 * O caminho: o dia da semana tira as aulas do quadro; o calendário
 * letivo diz em que bimestre e semana a data cai, para o ano de cada
 * turma; o escopo daquela semana, para o curso, o período e a
 * disciplina, dá as opções. Cada passo que não acha nada diz o motivo
 * na aula, e não some com ela.
 */
class PreverAulasDoDiaAction
{
    public function executar(User $professor, CarbonInterface $data): AulasDoDia
    {
        $dia = DiaDaSemana::daData($data);

        if ($dia === null) {
            return new AulasDoDia(collect(), "{$data->format('d/m/Y')} é domingo, e não há aula aos domingos.");
        }

        $quadro = QuadroHorario::query()
            ->where('professor_id', $professor->id)
            ->where('dia_semana', $dia)
            ->whereHas('turma', fn (Builder $q) => $q->where('status', StatusTurma::Ativa))
            ->with('turma:id,nome,curso_id,periodo,periodo_letivo', 'disciplina:id,nome')
            ->orderBy('hora_inicio')
            ->get();

        if ($quadro->isEmpty()) {
            return new AulasDoDia(collect(), "Você não tem aula no quadro de horários em {$data->format('d/m/Y')} "
                ."({$dia->rotulo()}). Se faltar alguma aula no quadro, avise a coordenação.");
        }

        // A posição de cada horário entre os da mesma disciplina na turma, na semana.
        $posicoes = QuadroHorario::query()
            ->whereIn('turma_id', $quadro->pluck('turma_id')->unique())
            ->whereIn('disciplina_id', $quadro->pluck('disciplina_id')->unique())
            ->orderBy('dia_semana')
            ->orderBy('aula_numero')
            ->get(['id', 'turma_id', 'disciplina_id'])
            ->groupBy(fn (QuadroHorario $h) => "{$h->turma_id}-{$h->disciplina_id}")
            ->map(fn ($horarios) => $horarios->pluck('id')->flip());

        /** @var array<string, SemanaLetiva> $locais */
        $locais = [];

        $aulas = $quadro->map(function (QuadroHorario $horario) use ($data, $posicoes, &$locais) {
            $turma = $horario->turma;
            $local = $locais[$turma->periodo_letivo] ??= BimestreLetivo::localizar($turma->periodo_letivo, $data);

            if (! $local->encontrada()) {
                return new AulaPrevista($horario, $local, collect(), null, $local->motivo);
            }

            $opcoes = EscopoAula::query()
                ->where('curso_id', $turma->curso_id)
                ->where('periodo', $turma->periodo)
                ->where('disciplina_id', $horario->disciplina_id)
                ->where('bimestre', $local->bimestre->value)
                ->where('semana', $local->semana)
                ->orderBy('ordem')
                ->get(['id', 'titulo_aula', 'ordem']);

            if ($opcoes->isEmpty()) {
                return new AulaPrevista($horario, $local, $opcoes, null,
                    "O escopo-sequência não tem aula de {$horario->disciplina->nome} para o {$turma->periodo}º período "
                    ."na semana {$local->semana} do {$local->bimestre->rotulo()}.");
            }

            $posicao = $posicoes["{$turma->id}-{$horario->disciplina_id}"][$horario->id] ?? 0;
            $sugerida = $opcoes->get($posicao);

            return new AulaPrevista($horario, $local, $opcoes, $sugerida, $sugerida !== null ? null
                : "O escopo traz {$opcoes->count()} aula(s) de {$horario->disciplina->nome} na semana {$local->semana}, "
                    .'e esta é a '.($posicao + 1)."ª da turma {$turma->nome} na semana. Escolha uma ou descreva o conteúdo.");
        });

        return new AulasDoDia($aulas);
    }
}
