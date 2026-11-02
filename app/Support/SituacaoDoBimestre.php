<?php

namespace App\Support;

use App\Models\BimestreLetivo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Em que ponto do ano letivo está um dia: dentro de um bimestre, entre
 * dois (férias ou recesso), ou sem calendário cadastrado.
 *
 * Os três casos são respostas diferentes, e a tela diz qual é. Sem
 * calendário não é "0 dias": é a falta do dado, e é dito assim.
 */
final readonly class SituacaoDoBimestre
{
    private function __construct(
        public CarbonInterface $hoje,
        public ?BimestreLetivo $atual,
        public ?BimestreLetivo $proximo,
    ) {}

    /** Calculada uma vez por requisição: a barra superior e o painel leem a mesma. */
    public static function hoje(): self
    {
        return once(fn () => self::em(today()));
    }

    public static function em(CarbonInterface $data): self
    {
        $dia = Carbon::parse($data)->startOfDay();

        $atual = BimestreLetivo::query()
            ->whereDate('inicio', '<=', $dia)
            ->whereDate('fim', '>=', $dia)
            ->first();

        $proximo = $atual !== null ? null : BimestreLetivo::query()
            ->whereDate('inicio', '>', $dia)
            ->orderBy('inicio')
            ->first();

        return new self($dia, $atual, $proximo);
    }

    public function emAndamento(): bool
    {
        return $this->atual !== null;
    }

    /** Dias até o fim do bimestre atual, ou até o início do próximo. Nulo sem calendário. */
    public function dias(): ?int
    {
        $alvo = $this->atual?->fim ?? $this->proximo?->inicio;

        return $alvo === null ? null : (int) $this->hoje->diffInDays($alvo->copy()->startOfDay());
    }

    /** "2º bimestre", "Férias" ou "Calendário não cadastrado". */
    public function rotulo(): string
    {
        return match (true) {
            $this->atual !== null => $this->atual->bimestre->rotulo(),
            $this->proximo !== null => 'Férias',
            default => 'Calendário não cadastrado',
        };
    }

    /** "faltam 12 dias", "termina hoje", "faltam 5 dias para o 3º bimestre". */
    public function prazo(): ?string
    {
        $dias = $this->dias();

        if ($this->atual !== null) {
            return match ($dias) {
                0 => 'termina hoje',
                1 => 'falta 1 dia',
                default => "faltam {$dias} dias",
            };
        }

        if ($this->proximo !== null) {
            $quando = $dias === 1 ? 'falta 1 dia' : "faltam {$dias} dias";

            return "{$quando} para o {$this->proximo->bimestre->rotulo()}";
        }

        return null;
    }

    /** "De 27/04/2026 a 03/07/2026" — o que o selo mostra ao passar o mouse. */
    public function periodo(): string
    {
        return match (true) {
            $this->atual !== null => "De {$this->atual->inicio->format('d/m/Y')} a {$this->atual->fim->format('d/m/Y')}",
            $this->proximo !== null => "O {$this->proximo->bimestre->rotulo()} começa em {$this->proximo->inicio->format('d/m/Y')}",
            default => 'Nenhum bimestre cadastrado para hoje ou adiante. O PAEET Admin cadastra as datas no calendário letivo.',
        };
    }

    /** A última semana pede atenção: notas e provas costumam fechar nela. */
    public function acabando(): bool
    {
        return $this->atual !== null && $this->dias() <= 7;
    }
}
