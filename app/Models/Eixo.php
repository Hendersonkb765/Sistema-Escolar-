<?php

namespace App\Models;

use App\Enums\StatusRegistro;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Eixo extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'eixos';

    protected $fillable = ['nome', 'codigo', 'status'];

    protected function casts(): array
    {
        return ['status' => StatusRegistro::class];
    }

    public static function caminhoDoEixo(): string
    {
        return '';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('eixo');
    }

    /** @return HasMany<Curso, $this> */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /** @return HasMany<Disciplina, $this> */
    public function disciplinas(): HasMany
    {
        return $this->hasMany(Disciplina::class);
    }

    /** @return HasMany<ModeloProva, $this> */
    public function modelosProva(): HasMany
    {
        return $this->hasMany(ModeloProva::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'eixo_usuario', 'eixo_id', 'usuario_id')
            ->withTimestamps();
    }
}
