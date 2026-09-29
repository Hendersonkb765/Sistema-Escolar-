<?php

namespace App\Models;

use App\Enums\StatusAluno;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Aluno extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'alunos';

    protected $fillable = ['turma_id', 'nome', 'ra', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo'];

    protected function casts(): array
    {
        return ['status' => StatusAluno::class];
    }

    public static function caminhoDoEixo(): string
    {
        return 'turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('aluno');
    }

    /** O professor vê alunos apenas das turmas em que atua. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        /*
         * Pelas PARTES da solicitação, e não pela solicitação: quem responde
         * uma disciplina é a parte, e `solicitacoes_prova` não tem
         * `professor_id`. O erro não aparecia — no SQLite um identificador
         * entre aspas que não resolve para coluna nenhuma vira texto
         * literal, e `'professor_id' = 5` é apenas falso. No MySQL seria
         * "Unknown column".
         */
        return $query->whereHas(
            'turma.solicitacoes.partes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return HasMany<AlunoHistorico, $this> */
    public function historicos(): HasMany
    {
        return $this->hasMany(AlunoHistorico::class);
    }

    /** @return HasMany<ResultadoAluno, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoAluno::class);
    }
}
