<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O escopo-sequência: o que se ensina em cada semana do curso.
     *
     * Uma linha é uma aula. O documento vem pronto de fora — a escola
     * recebe a planilha e não a escreve —, e por isso quase tudo aqui é
     * texto guardado como veio. Só o que a tela precisa para ordenar e
     * filtrar é interpretado: bimestre, semana e os códigos.
     *
     * Inventar significado para "Sim/Não" ou "2/1" numa coluna que
     * ninguém daqui controla é como o texto do arquivo vira um dado
     * errado que parece certo.
     */
    public function up(): void
    {
        Schema::create('escopo_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            // Período do curso. Não vem na planilha: quem importa informa.
            $table->unsignedTinyInteger('periodo');

            /*
             * Preenchida quando o código do componente casa com uma
             * disciplina cadastrada. Nula não é erro: o escopo chega da
             * rede antes de a escola cadastrar tudo, e a aula continua
             * valendo — só não entra no filtro "as minhas".
             */
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();

            $table->unsignedTinyInteger('bimestre');
            $table->unsignedSmallInteger('semana');
            // Posição da aula na planilha: é a ordem em que ela é dada.
            $table->unsignedSmallInteger('ordem');

            $table->string('componente_nome');
            $table->string('componente_codigo', 60)->nullable();
            $table->string('unidade_nome')->nullable();
            $table->string('unidade_codigo', 60)->nullable();

            $table->string('titulo_aula');
            $table->text('tema_semana')->nullable();
            $table->text('objetivos')->nullable();
            $table->text('objeto_conhecimento')->nullable();
            $table->text('habilidades_tecnicas')->nullable();
            $table->text('habilidades_socioemocionais')->nullable();
            $table->text('competencia_tecnica')->nullable();
            $table->text('competencias_socioemocionais')->nullable();

            // Como vieram: "2/1", "Sim", "4"… a planilha é de fora.
            $table->string('carga_horaria', 60)->nullable();
            $table->string('aula_complementar', 60)->nullable();
            $table->string('aulas_semanais', 60)->nullable();

            $table->timestamps();

            $table->index(['curso_id', 'periodo', 'bimestre', 'semana'], 'escopo_percurso');
            $table->index('disciplina_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escopo_aulas');
    }
};
