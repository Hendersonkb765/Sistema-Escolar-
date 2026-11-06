<?php

namespace App\Models;

use App\Enums\StatusRegistro;
use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Models\Concerns\VaiParaALixeira;
use App\Models\Contracts\Excluivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Disciplina de um curso, cursada em um período determinado.
 *
 * O período vive aqui, no cadastro — é o estado atual e editável. As
 * grades curriculares são fotos desse estado, tiradas ao publicar uma
 * versão, e é nelas que as turmas se apoiam para não terem o percurso
 * reescrito quando o cadastro muda.
 */
class Disciplina extends Model implements Excluivel
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use VaiParaALixeira;

    protected $table = 'disciplinas';

    protected $fillable = ['curso_id', 'nome', 'codigo', 'periodo', 'carga_horaria', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo', 'carga_horaria' => 80];

    protected function casts(): array
    {
        return [
            'status' => StatusRegistro::class,
            'periodo' => 'integer',
            'carga_horaria' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('disciplina');
    }

    /** O professor vê as disciplinas em que tem vínculo docente ativo. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereIn('disciplinas.id', $usuario->disciplinaIds());
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @return HasMany<GradeDisciplina, $this> */
    public function gradeDisciplinas(): HasMany
    {
        return $this->hasMany(GradeDisciplina::class);
    }

    /** Docentes vinculados — professores e PAEETs que também lecionam. */
    public function professores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'professor_disciplina', 'disciplina_id', 'usuario_id')
            ->withPivot(['turma_id', 'ativo'])
            ->withTimestamps();
    }

    /** @return Builder<Disciplina> */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status', StatusRegistro::Ativo);
    }

    /** @return Builder<Disciplina> */
    public function scopeDoPeriodo(Builder $query, int $periodo): Builder
    {
        return $query->where('periodo', $periodo);
    }

    // --- Lixeira -----------------------------------------------------------

    public function rotuloParaExclusao(): string
    {
        return "Disciplina {$this->nome}";
    }

    /**
     * As aulas dela no quadro saem de fato: a aula ocupa um horário da
     * turma, e na Lixeira continuaria ocupando. A cópia volta na
     * restauração.
     *
     * Os vínculos de professor ficam: o cadastro de usuários os desativa,
     * nunca apaga — é o histórico de quem lecionou o quê.
     */
    public function retiradosNaExclusao(): Collection
    {
        return QuadroHorario::query()->where('disciplina_id', $this->id)->get();
    }

    public function avisosDaExclusao(): array
    {
        $grades = $this->gradeDisciplinas()->distinct()->count('grade_curricular_id');
        $questoes = Questao::query()->where('disciplina_id', $this->id)->count();

        return array_values(array_filter([
            $grades > 0 ? "Continua nas {$grades} grade(s) já publicada(s), que são fotos e não mudam; a próxima versão sai sem ela." : null,
            $questoes > 0 ? "{$questoes} questão(ões) já escrita(s) continuam no banco, com o nome dela." : null,
        ]));
    }

    public function paiNaRestauracao(): ?Model
    {
        return $this->curso()->withTrashed()->first();
    }
}
