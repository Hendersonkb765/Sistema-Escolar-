<?php

namespace App\Livewire;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Painel inicial. Os indicadores completos de cada perfil chegam no
 * milestone 8; por ora mostra o estado da estrutura dentro do escopo.
 */
class Painel extends Component
{
    public function render(): View
    {
        $usuario = auth()->user();

        return view('painel.index', [
            'usuario' => $usuario,
            'indicadores' => $this->indicadores($usuario),
        ])->layout('components.layouts.app', [
            'titulo' => 'Painel',
            'subtitulo' => $this->descricaoDoEscopo($usuario),
        ]);
    }

    /** @return array<int, array{rotulo: string, valor: int|string, cor: string, href: ?string}> */
    protected function indicadores(User $usuario): array
    {
        if (! $usuario->ehGestao()) {
            return [
                [
                    'rotulo' => 'Disciplinas que leciono',
                    'valor' => count($usuario->disciplinaIds()),
                    'cor' => 'azul',
                    'href' => null,
                ],
            ];
        }

        $indicadores = [
            [
                'rotulo' => 'Eixos no meu escopo',
                'valor' => count($usuario->eixoIds()),
                'cor' => 'roxo',
                'href' => Gate::allows('viewAny', Eixo::class) ? route('eixos.index') : null,
            ],
            [
                'rotulo' => 'Cursos',
                'valor' => Curso::query()->visivelPara($usuario)->count(),
                'cor' => 'azul',
                'href' => route('cursos.index'),
            ],
            [
                'rotulo' => 'Disciplinas',
                'valor' => Disciplina::query()->visivelPara($usuario)->count(),
                'cor' => 'azul',
                'href' => null,
            ],
            [
                'rotulo' => 'Turmas ativas',
                'valor' => Turma::query()->visivelPara($usuario)->where('status', 'ativa')->count(),
                'cor' => 'verde',
                'href' => null,
            ],
            [
                'rotulo' => 'Alunos',
                'valor' => Aluno::query()->visivelPara($usuario)->count(),
                'cor' => 'cinza',
                'href' => null,
            ],
        ];

        if (Gate::allows('viewAny', User::class)) {
            $indicadores[] = [
                'rotulo' => 'Usuários',
                'valor' => $this->contarUsuariosVisiveis($usuario),
                'cor' => 'cinza',
                'href' => route('usuarios.index'),
            ];
        }

        return $indicadores;
    }

    protected function contarUsuariosVisiveis(User $usuario): int
    {
        return User::query()
            ->where(function ($consulta) use ($usuario) {
                $consulta
                    ->whereHas('eixos', fn ($q) => $q->whereIn('eixos.id', $usuario->eixoIds()))
                    ->orWhere('criado_por', $usuario->getKey())
                    ->orWhere('usuarios.id', $usuario->getKey());
            })
            ->count();
    }

    protected function descricaoDoEscopo(User $usuario): string
    {
        if (! $usuario->ehGestao()) {
            return 'Área do professor';
        }

        $eixos = $usuario->eixos()->orderBy('nome')->pluck('nome');

        return $eixos->isEmpty()
            ? 'Nenhum Eixo vinculado à sua conta'
            : 'Eixos: '.$eixos->join(', ');
    }
}
