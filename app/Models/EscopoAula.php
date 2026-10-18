<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma aula do escopo-sequência: o que se ensina na semana tal.
 *
 * O documento vem pronto da rede, e a escola o recebe — não o escreve.
 * Por isso o texto é guardado como veio e só bimestre, semana e os
 * códigos são interpretados: é do que a tela precisa para ordenar e
 * filtrar.
 *
 * `disciplina_id` é preenchida quando o código do componente casa com
 * uma disciplina do curso. Nula não é erro — é escopo que chegou antes
 * do cadastro, e a aula continua valendo.
 */
class EscopoAula extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'escopo_aulas';

    protected $fillable = [
        'curso_id', 'periodo', 'disciplina_id', 'bimestre', 'semana', 'ordem',
        'componente_nome', 'componente_codigo', 'unidade_nome', 'unidade_codigo',
        'titulo_aula', 'tema_semana', 'objetivos', 'objeto_conhecimento',
        'habilidades_tecnicas', 'habilidades_socioemocionais',
        'competencia_tecnica', 'competencias_socioemocionais',
        'carga_horaria', 'aula_complementar', 'aulas_semanais',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'integer',
            'bimestre' => 'integer',
            'semana' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    /**
     * O professor vê as aulas das matérias que leciona, e só elas.
     *
     * Pela disciplina, e não pelo curso: o escopo de um curso tem seis
     * componentes e mais de quinhentas aulas, e quem dá Programação
     * Mobile não veio ler o percurso de banco de dados.
     *
     * A aula só entra quando o componente da planilha casou com uma
     * disciplina do cadastro. Componente que não casou fica invisível
     * ao professor — por isso a conferência da importação lista quais
     * não casaram, e a tela diz quando não há nada para mostrar.
     */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereIn('disciplina_id', $usuario->disciplinaIds());
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /**
     * Um rótulo para o componente, com o código quando ele veio.
     *
     * É por ele que a tela agrupa: dois componentes de nomes parecidos
     * em cursos diferentes se distinguem pelo código.
     */
    public function componente(): string
    {
        return $this->componente_codigo === null
            ? $this->componente_nome
            : $this->componente_nome.' ('.$this->componente_codigo.')';
    }
}
