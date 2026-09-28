<?php

namespace App\Support;

use App\Enums\StatusQuestao;
use App\Models\Aluno;
use App\Models\CompartilhamentoDeModelo;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Importacao;
use App\Models\ModeloDocumento;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Monta a navegação lateral a partir das Policies. Um item só aparece
 * quando o usuário realmente pode acessá-lo — a decisão é a mesma que o
 * `authorize()` da rota aplica, e não um `@if` solto no Blade.
 */
class Navegacao
{
    /** @return array<int, array{titulo: ?string, itens: array<int, array{rotulo: string, url: string, icone: string, ativo: bool}>}> */
    public static function paraUsuario(User $usuario): array
    {
        $grupos = [
            [
                'titulo' => null,
                'itens' => [
                    self::item('Painel', 'painel', self::icone('painel')),
                ],
            ],
            [
                'titulo' => 'Estrutura acadêmica',
                'itens' => array_filter([
                    Gate::forUser($usuario)->allows('viewAny', Eixo::class)
                        ? self::item('Eixos', 'eixos.index', self::icone('eixo')) : null,
                    Gate::forUser($usuario)->allows('viewAny', Curso::class)
                        ? self::item('Cursos', 'cursos.index', self::icone('curso')) : null,
                    Gate::forUser($usuario)->allows('viewAny', Disciplina::class)
                        ? self::item('Disciplinas', 'disciplinas.index', self::icone('disciplina')) : null,
                    Gate::forUser($usuario)->allows('viewAny', GradeCurricular::class)
                        ? self::item('Grades curriculares', 'grades.index', self::icone('grade')) : null,
                    Gate::forUser($usuario)->allows('viewAny', Turma::class)
                        ? self::item('Turmas', 'turmas.index', self::icone('turma')) : null,
                    Gate::forUser($usuario)->allows('viewAny', Aluno::class)
                        ? self::item('Alunos', 'alunos.index', self::icone('aluno')) : null,
                ]),
            ],
            [
                'titulo' => 'Avaliações',
                'itens' => array_filter([
                    Gate::forUser($usuario)->allows('viewAny', SolicitacaoProva::class)
                        ? self::item(
                            $usuario->ehGestao() ? 'Solicitações' : 'Minhas solicitações',
                            'solicitacoes.index',
                            self::icone('solicitacao')
                        ) : null,
                    Gate::forUser($usuario)->allows('viewAny', Questao::class)
                        ? self::item(
                            $usuario->ehGestao() ? 'Análise de questões' : 'Minhas questões',
                            'questoes.index',
                            self::icone('questao'),
                            // Um professor precisa ver que algo voltou para
                            // ele sem abrir solicitação por solicitação.
                            contador: self::contadorDeQuestoes($usuario),
                        ) : null,
                    Gate::forUser($usuario)->allows('viewAny', Prova::class)
                        ? self::item('Provas', 'provas.index', self::icone('prova')) : null,
                    Gate::forUser($usuario)->allows('viewAny', ModeloProva::class)
                        ? self::item('Modelos de prova', 'modelos-prova.index', self::icone('modelo')) : null,
                    Gate::forUser($usuario)->allows('viewAny', Importacao::class)
                        ? self::item('Importar resultados', 'importacoes.index', self::icone('importacao')) : null,
                    Gate::forUser($usuario)->allows('viewAny', ResultadoAluno::class)
                        ? self::item('Resultados e notas', 'resultados.index', self::icone('resultado')) : null,
                    Gate::forUser($usuario)->allows('viewAny', ResultadoAluno::class)
                        ? self::item('Análise de desempenho', 'analises.index', self::icone('analise')) : null,
                ]),
            ],
            [
                'titulo' => 'Documentos do aluno',
                'itens' => array_filter([
                    Gate::forUser($usuario)->allows('viewAny', ModeloDocumento::class)
                        ? self::item('Modelos de documento', 'documentos.index', self::icone('documento')) : null,
                    Gate::forUser($usuario)->allows('viewAny', ModeloDocumento::class)
                        ? self::item('Gerar documentos', 'documentos.gerar', self::icone('gerar')) : null,
                    Gate::forUser($usuario)->allows('viewAny', CompartilhamentoDeModelo::class)
                        ? self::item(
                            'Modelos compartilhados',
                            'documentos.compartilhados',
                            self::icone('compartilhar'),
                            // Uma oferta parada é alguém esperando resposta.
                            contador: self::contadorDeCompartilhamentos($usuario),
                        ) : null,
                ]),
            ],
            [
                'titulo' => 'Administração',
                'itens' => array_filter([
                    Gate::forUser($usuario)->allows('viewAny', User::class)
                        ? self::item('Usuários', 'usuarios.index', self::icone('usuario')) : null,
                    $usuario->ehGestao()
                        ? self::item('Auditoria', 'auditoria.index', self::icone('auditoria')) : null,
                ]),
            ],
        ];

        return array_values(array_filter(
            array_map(
                fn (array $grupo) => [...$grupo, 'itens' => array_values($grupo['itens'])],
                $grupos
            ),
            fn (array $grupo) => $grupo['itens'] !== []
        ));
    }

    /** @return array{rotulo: string, url: string, icone: string, ativo: bool, contador: ?array{valor: int, cor: string, titulo: string}} */
    protected static function item(
        string $rotulo,
        string $rota,
        string $icone,
        ?array $contador = null,
    ): array {
        $prefixo = Str::before($rota, '.');

        return [
            'rotulo' => $rotulo,
            'url' => Route::has($rota) ? route($rota) : '#',
            'icone' => $icone,
            'ativo' => request()->routeIs($prefixo.'*'),
            'contador' => $contador,
        ];
    }

    /**
     * Selo ao lado de "Modelos compartilhados": ofertas que estão
     * paradas esperando esta pessoa responder.
     *
     * @return array{valor: int, cor: string, titulo: string}|null
     */
    protected static function contadorDeCompartilhamentos(User $usuario): ?array
    {
        if (! $usuario->ehGestao()) {
            return null;
        }

        $pendentes = CompartilhamentoDeModelo::query()
            ->recebidosPor($usuario)
            ->pendentes()
            ->count();

        return $pendentes === 0 ? null : [
            'valor' => $pendentes,
            'cor' => 'amarelo',
            'titulo' => $pendentes === 1
                ? '1 modelo aguardando a sua resposta'
                : "{$pendentes} modelos aguardando a sua resposta",
        ];
    }

    /**
     * Selo ao lado de "Minhas questões" / "Análise de questões": o que
     * exige ação de quem está olhando.
     *
     * @return array{valor: int, cor: string, titulo: string}|null
     */
    protected static function contadorDeQuestoes(User $usuario): ?array
    {
        if (! $usuario->ehGestao()) {
            $devolvidas = $usuario->questoesDevolvidas();

            return $devolvidas === 0 ? null : [
                'valor' => $devolvidas,
                'cor' => 'vermelho',
                'titulo' => $devolvidas === 1
                    ? '1 questão devolvida para correção'
                    : "{$devolvidas} questões devolvidas para correção",
            ];
        }

        $aguardando = Questao::query()
            ->visivelPara($usuario)
            ->whereIn('status', [
                StatusQuestao::Enviada->value,
                StatusQuestao::EmAnalise->value,
            ])
            ->count();

        return $aguardando === 0 ? null : [
            'valor' => $aguardando,
            'cor' => 'azul',
            'titulo' => $aguardando === 1
                ? '1 questão aguardando análise'
                : "{$aguardando} questões aguardando análise",
        ];
    }

    protected static function icone(string $chave): string
    {
        $caminhos = [
            'painel' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
            'eixo' => 'M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3',
            'curso' => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
            'disciplina' => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292',
            'grade' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
            'turma' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z',
            'aluno' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
            'solicitacao' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
            'questao' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z',
            'prova' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            'modelo' => 'M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125z',
            'importacao' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
            'analise' => 'M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z',
            'documento' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            'gerar' => 'M7.5 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75h-1.5m-9 0V3m9 .75V3M12 12.75v-6m0 0l-2.25 2.25M12 6.75l2.25 2.25',
            'compartilhar' => 'M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z',
            'resultado' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
            'usuario' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            'auditoria' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        ];

        $d = $caminhos[$chave] ?? $caminhos['painel'];

        return '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';
    }
}
