<?php

namespace App\Models;

use App\Enums\Bimestre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * As datas de um bimestre numa turma com calendário próprio. Tendo
 * alguma, a turma deixa de seguir o calendário letivo da escola.
 */
class TurmaBimestre extends Model
{
    use LogsActivity;

    protected $table = 'turma_bimestres';

    protected $fillable = ['turma_id', 'bimestre', 'inicio', 'fim'];

    protected function casts(): array
    {
        return [
            'bimestre' => Bimestre::class,
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('calendario');
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }
}
