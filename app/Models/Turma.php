<?php

namespace App\Models;

use App\Enums\StatusTurma;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Turma extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'turmas';

    protected $fillable = [
        'curso_id',
        'grade_curricular_id',
        'periodo',
        'nome',
        'periodo_letivo',
        'status',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativa'];

    protected function casts(): array
    {
        return [
            'status' => StatusTurma::class,
            'periodo' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('turma');
    }

    /** O professor vê as turmas para as quais recebeu solicitações. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'solicitacoes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** Versão da grade congelada nesta turma. */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(GradeCurricular::class, 'grade_curricular_id');
    }

    /** @return HasMany<Aluno, $this> */
    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    /** @return HasMany<TurmaHistorico, $this> */
    public function historicos(): HasMany
    {
        return $this->hasMany(TurmaHistorico::class);
    }

    /** @return HasMany<SolicitacaoProva, $this> */
    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoProva::class);
    }

    /** @return HasMany<Prova, $this> */
    public function provas(): HasMany
    {
        return $this->hasMany(Prova::class);
    }

    /**
     * Disciplinas do período corrente da turma, segundo a foto de grade
     * congelada nela.
     *
     * @return Collection<int, GradeDisciplina>
     */
    public function disciplinasDoPeriodo(?int $periodo = null): Collection
    {
        // loadMissing: o método é chamado de views, actions e comandos, e
        // nem todos passam pela consulta que já traz a grade.
        return $this->loadMissing('grade')->grade
            ->disciplinas()
            ->with('disciplina')
            ->where('periodo', $periodo ?? $this->periodo)
            ->orderBy('id')
            ->get();
    }

    /** Último período previsto pelo curso. */
    public function periodoFinal(): int
    {
        return (int) $this->loadMissing('curso')->curso->duracao_anos;
    }

    public function podeAvancar(): bool
    {
        return $this->status === StatusTurma::Ativa
            && $this->periodo < $this->periodoFinal();
    }

    /**
     * Sugere o nome do próximo período trocando o prefixo numérico:
     * "2 A" vira "3 A". O nome é livre, então quando não começa por
     * dígito o valor atual é mantido e cabe ao usuário ajustar.
     */
    public function nomeParaPeriodo(int $periodo): string
    {
        return preg_match('/^\\d+/', $this->nome) === 1
            ? preg_replace('/^\\d+/', (string) $periodo, $this->nome)
            : $this->nome;
    }
}
