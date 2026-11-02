<?php

namespace App\Support;

use App\Enums\DiaDaSemana;
use App\Models\Turma;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Um intervalo do dia da escola, e a quais turmas ele se aplica.
 *
 * Os intervalos são da Configuração da escola, que só o PAEET Admin
 * altera. Cada um vale para todas as turmas — o padrão —, para todas
 * menos algumas, ou só para algumas. É assim que uma turma com horário
 * diferente ganha o intervalo dela sem cadastro espalhado.
 */
final readonly class IntervaloDoDia
{
    public const TODAS = 'todas';

    public const EXCETO = 'exceto';

    public const SOMENTE = 'somente';

    /**
     * @param  array<int, int>  $turmas  as marcadas: excluídas em EXCETO, as únicas em SOMENTE
     */
    public function __construct(
        public string $nome,
        public string $inicio,
        public string $fim,
        public string $alcance = self::TODAS,
        public array $turmas = [],
    ) {}

    /**
     * Todos os intervalos da escola, de `instituicao.intervalos`.
     *
     * @return Collection<int, self>
     */
    public static function padrao(): Collection
    {
        return collect(config('instituicao.intervalos'))
            ->map(fn (array $i) => new self(
                $i['nome'],
                $i['inicio'],
                Carbon::createFromFormat('H:i', $i['inicio'])->addMinutes($i['minutos'])->format('H:i'),
                $i['alcance'] ?? self::TODAS,
                array_map('intval', $i['turmas'] ?? []),
            ))
            ->sortBy('inicio')
            ->values();
    }

    /**
     * Os intervalos que valem para a turma.
     *
     * @return Collection<int, self>
     */
    public static function daTurma(Turma $turma): Collection
    {
        return self::padrao()->filter(fn (self $i) => $i->aplicaA($turma->id))->values();
    }

    public function aplicaA(int $turmaId): bool
    {
        return match ($this->alcance) {
            self::EXCETO => ! in_array($turmaId, $this->turmas, true),
            self::SOMENTE => in_array($turmaId, $this->turmas, true),
            default => true,
        };
    }

    /** O intervalo está acontecendo agora. Domingo não tem intervalo, como não tem aula. */
    public function acontecendoEm(CarbonInterface $momento): bool
    {
        $hora = $momento->format('H:i');

        return DiaDaSemana::daData($momento) !== null && $this->inicio <= $hora && $hora < $this->fim;
    }

    /** "17:00–17:20" */
    public function intervalo(): string
    {
        return "{$this->inicio}–{$this->fim}";
    }

    /** Duração em minutos. */
    public function minutos(): int
    {
        return (int) Carbon::createFromFormat('H:i', $this->inicio)->diffInMinutes(Carbon::createFromFormat('H:i', $this->fim));
    }
}
