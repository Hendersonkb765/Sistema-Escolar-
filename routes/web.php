<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ModuloEmConstrucaoController;
use App\Livewire\Alunos\FormularioAluno;
use App\Livewire\Alunos\ListaAlunos;
use App\Livewire\Cursos\DetalheCurso;
use App\Livewire\Cursos\FormularioCurso;
use App\Livewire\Cursos\ListaCursos;
use App\Livewire\Disciplinas\FormularioDisciplina;
use App\Livewire\Disciplinas\ListaDisciplinas;
use App\Livewire\Eixos\FormularioEixo;
use App\Livewire\Eixos\ListaEixos;
use App\Livewire\Grades\DetalheGrade;
use App\Livewire\Grades\ListaGrades;
use App\Livewire\Painel;
use App\Livewire\Turmas\DetalheTurma;
use App\Livewire\Turmas\FormularioTurma;
use App\Livewire\Turmas\ListaTurmas;
use App\Livewire\Usuarios\FormularioUsuario;
use App\Livewire\Usuarios\ListaUsuarios;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Regra de ouro
|--------------------------------------------------------------------------
| Não existe rota de cadastro público. `Features::registration()` está
| desligada em config/fortify.php, nenhuma rota `register` é declarada aqui
| e não há tela, link ou convite de auto-inscrição em lugar nenhum.
| Uma conta só nasce em /usuarios/criar, sob a Policy de usuários.
*/

Route::redirect('/', '/painel')->name('inicio');

Route::middleware(['auth', 'ativo'])->group(function () {
    Route::get('/painel', Painel::class)->name('painel');

    // --- Estrutura acadêmica -------------------------------------------
    Route::get('/eixos', ListaEixos::class)->name('eixos.index');
    Route::get('/eixos/criar', FormularioEixo::class)->name('eixos.criar');
    Route::get('/eixos/{eixo}/editar', FormularioEixo::class)->name('eixos.editar');

    Route::get('/cursos', ListaCursos::class)->name('cursos.index');
    Route::get('/cursos/criar', FormularioCurso::class)->name('cursos.criar');
    Route::get('/cursos/{curso}', DetalheCurso::class)->name('cursos.show');
    Route::get('/cursos/{curso}/editar', FormularioCurso::class)->name('cursos.editar');

    Route::get('/disciplinas', ListaDisciplinas::class)->name('disciplinas.index');
    Route::get('/disciplinas/criar', FormularioDisciplina::class)->name('disciplinas.criar');
    Route::get('/disciplinas/{disciplina}/editar', FormularioDisciplina::class)->name('disciplinas.editar');

    // A grade é publicada a partir do curso (foto das disciplinas), então
    // aqui só se consulta.
    Route::get('/grades', ListaGrades::class)->name('grades.index');
    Route::get('/grades/{grade}', DetalheGrade::class)->name('grades.show');

    Route::get('/turmas', ListaTurmas::class)->name('turmas.index');
    Route::get('/turmas/criar', FormularioTurma::class)->name('turmas.criar');
    Route::get('/turmas/{turma}', DetalheTurma::class)->name('turmas.show');
    Route::get('/turmas/{turma}/editar', FormularioTurma::class)->name('turmas.editar');

    Route::get('/alunos', ListaAlunos::class)->name('alunos.index');
    Route::get('/alunos/criar', FormularioAluno::class)->name('alunos.criar');
    Route::get('/alunos/{aluno}/editar', FormularioAluno::class)->name('alunos.editar');

    // --- Avaliações (milestones 3 a 7) -----------------------------------
    Route::get('/solicitacoes', ModuloEmConstrucaoController::class)->name('solicitacoes.index');
    Route::get('/solicitacoes/criar', ModuloEmConstrucaoController::class)->name('solicitacoes.criar');

    Route::get('/questoes', ModuloEmConstrucaoController::class)->name('questoes.index');

    Route::get('/provas', ModuloEmConstrucaoController::class)->name('provas.index');
    Route::get('/provas/criar', ModuloEmConstrucaoController::class)->name('provas.criar');
    Route::get('/modelos-prova', ModuloEmConstrucaoController::class)->name('modelos-prova.index');

    Route::get('/importacoes', ModuloEmConstrucaoController::class)->name('importacoes.index');
    Route::get('/importacoes/criar', ModuloEmConstrucaoController::class)->name('importacoes.criar');

    Route::get('/resultados', ModuloEmConstrucaoController::class)->name('resultados.index');

    // --- Administração ---------------------------------------------------
    Route::get('/usuarios', ListaUsuarios::class)->name('usuarios.index');
    Route::get('/usuarios/criar', FormularioUsuario::class)->name('usuarios.criar');
    Route::get('/usuarios/{usuario}/editar', FormularioUsuario::class)->name('usuarios.editar');

    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
})->scopeBindings();
