<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GradeDisciplina extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;

    protected $table = 'grade_disciplinas';

    protected $fillable = [
        'grade_curricular_id',
        'disciplina_id',
        'periodo',
        'carga_horaria',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'integer',
            'carga_horaria' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'grade.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('grade');
    }

    /** @return BelongsTo<GradeCurricular, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(GradeCurricular::class, 'grade_curricular_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }
}
