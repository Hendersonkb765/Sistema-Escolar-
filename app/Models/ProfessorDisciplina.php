<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Vínculo docente. Independe do perfil: um PAEET que também leciona é
 * registrado aqui, sem precisar de uma segunda conta.
 */
class ProfessorDisciplina extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;

    protected $table = 'professor_disciplina';

    protected $fillable = ['usuario_id', 'disciplina_id', 'turma_id', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public static function caminhoDoEixo(): string
    {
        return 'disciplina.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('vinculo_docente');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }
}
