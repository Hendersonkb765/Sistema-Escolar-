<?php

namespace App\Models;

use App\Enums\StatusRegistro;
use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Models\Concerns\VaiParaALixeira;
use App\Models\Contracts\Excluivel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Eixo extends Model implements Excluivel
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use VaiParaALixeira;

    protected $table = 'eixos';

    protected $fillable = ['nome', 'codigo', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo'];

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

    /** Disciplinas do eixo, alcançadas através dos cursos. */
    public function disciplinas(): HasManyThrough
    {
        return $this->hasManyThrough(Disciplina::class, Curso::class);
    }

    /** @return HasMany<ModeloDocumento, $this> */
    public function modelosDocumento(): HasMany
    {
        return $this->hasMany(ModeloDocumento::class);
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

    // --- Lixeira -----------------------------------------------------------

    public function rotuloParaExclusao(): string
    {
        return "Eixo {$this->nome}";
    }

    /** Os cursos (com tudo o que é deles) e os modelos de prova e de documento. */
    public function dependentesNaExclusao(): Collection
    {
        return collect()
            ->concat($this->cursos()->get())
            ->concat($this->modelosProva()->get())
            ->concat($this->modelosDocumento()->get());
    }

    public function avisosDaExclusao(): array
    {
        $usuarios = $this->usuarios()->count();

        return $usuarios > 0
            ? ["{$usuarios} usuário(s) vinculado(s) deixam de ver o que é deste Eixo enquanto ele estiver na Lixeira."]
            : [];
    }

    /** Leva cursos inteiros: pede duas confirmações. */
    public function confirmacoesParaExcluir(): int
    {
        return 2;
    }
}
