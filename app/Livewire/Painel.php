<?php

namespace App\Livewire;

use App\Enums\StatusSolicitacao;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\SolicitacaoProva;
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
            return $this->indicadoresDoProfessor($usuario);
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

        return [...$this->indicadoresDeAvaliacao($usuario), ...$indicadores];
    }

    /**
     * O que a gestão precisa ver primeiro: o que está pendente com os
     * professores e o que já passou do prazo.
     *
     * @return array<int, array{rotulo: string, valor: int|string, cor: string, href: ?string, detalhe?: string}>
     */
    protected function indicadoresDeAvaliacao(User $usuario): array
    {
        $visiveis = fn () => SolicitacaoProva::query()->visivelPara($usuario);

        $aguardando = (clone $visiveis())
            ->where('status', StatusSolicitacao::Aberta)
            ->whereNull('enviada_em')
            ->count();

        $atrasadas = (clone $visiveis())
            ->whereNull('enviada_em')
            ->where('prazo', '<', now())
            ->whereIn('status', [
                StatusSolicitacao::Aberta->value,
                StatusSolicitacao::Enviada->value,
                StatusSolicitacao::EmAnalise->value,
            ])
            ->count();

        $aguardandoAnalise = (clone $visiveis())
            ->whereIn('status', [StatusSolicitacao::Enviada->value, StatusSolicitacao::EmAnalise->value])
            ->count();

        return [
            [
                'rotulo' => 'Solicitações aguardando envio',
                'valor' => $aguardando,
                'cor' => $aguardando > 0 ? 'amarelo' : 'verde',
                'href' => route('solicitacoes.index', ['prazo' => 'pendentes']),
            ],
            [
                'rotulo' => 'Atrasadas',
                'valor' => $atrasadas,
                'cor' => $atrasadas > 0 ? 'vermelho' : 'verde',
                'href' => route('solicitacoes.index', ['prazo' => 'atrasadas']),
                'detalhe' => 'Prazo vencido não bloqueia o envio',
            ],
            [
                'rotulo' => 'Questões aguardando análise',
                'valor' => $aguardandoAnalise,
                'cor' => $aguardandoAnalise > 0 ? 'azul' : 'cinza',
                'href' => route('solicitacoes.index', ['situacao' => StatusSolicitacao::Enviada->value]),
            ],
        ];
    }

    /**
     * Área do professor: o que lhe foi pedido, o que está atrasado e o
     * que já seguiu para análise.
     *
     * @return array<int, array{rotulo: string, valor: int|string, cor: string, href: ?string, detalhe?: string}>
     */
    protected function indicadoresDoProfessor(User $usuario): array
    {
        $minhas = fn () => SolicitacaoProva::query()->visivelPara($usuario);

        $aResponder = (clone $minhas())
            ->where('status', StatusSolicitacao::Aberta)
            ->whereNull('enviada_em')
            ->count();

        $atrasadas = (clone $minhas())
            ->where('status', StatusSolicitacao::Aberta)
            ->whereNull('enviada_em')
            ->where('prazo', '<', now())
            ->count();

        $enviadas = (clone $minhas())->whereNotNull('enviada_em')->count();

        $proximoPrazo = (clone $minhas())
            ->where('status', StatusSolicitacao::Aberta)
            ->whereNull('enviada_em')
            ->orderBy('prazo')
            ->value('prazo');

        return [
            [
                'rotulo' => 'A responder',
                'valor' => $aResponder,
                'cor' => $aResponder > 0 ? 'amarelo' : 'verde',
                'href' => route('solicitacoes.index'),
                'detalhe' => $proximoPrazo !== null
                    ? 'Próximo prazo: '.now()->parse($proximoPrazo)->format('d/m/Y')
                    : null,
            ],
            [
                'rotulo' => 'Com prazo vencido',
                'valor' => $atrasadas,
                'cor' => $atrasadas > 0 ? 'vermelho' : 'verde',
                'href' => route('solicitacoes.index', ['prazo' => 'atrasadas']),
                'detalhe' => 'Você ainda pode enviar',
            ],
            [
                'rotulo' => 'Já enviadas',
                'valor' => $enviadas,
                'cor' => 'azul',
                'href' => route('solicitacoes.index'),
            ],
            [
                'rotulo' => 'Disciplinas que leciono',
                'valor' => count($usuario->disciplinaIds()),
                'cor' => 'cinza',
                'href' => null,
            ],
        ];
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
