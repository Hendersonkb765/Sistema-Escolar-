<?php

namespace App\Livewire;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Questao;
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

        /*
         * "Eixos no meu escopo: 1" não informa nada a quem tem um Eixo
         * só — e é o caso do PAEET. O subtítulo já diz qual é. O cartão
         * fica para quem tem mais de um, onde o número é notícia.
         */
        $indicadores = count($usuario->eixoIds()) > 1 ? [
            [
                'rotulo' => 'Eixos no meu escopo',
                'valor' => count($usuario->eixoIds()),
                'cor' => 'roxo',
                'href' => Gate::allows('viewAny', Eixo::class) ? route('eixos.index') : null,
            ],
        ] : [];

        $indicadores = [
            ...$indicadores,
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
                // Pelo mesmo escopo da tela de usuários, que é para
                // onde este número leva: se divergirem, o painel mente.
                'valor' => User::query()->visivelPara($usuario)->count(),
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

        /*
         * Pelos escopos do model, que são os mesmos da listagem para
         * onde cada número aponta. Aqui havia `whereNull('enviada_em')`
         * direto na solicitação — coluna que mora nas partes —, e os
         * dois contadores viviam zerados sem reclamar.
         */
        $aguardando = (clone $visiveis())
            ->where('status', StatusSolicitacao::Aberta)
            ->comPartePendente()
            ->count();

        $atrasadas = (clone $visiveis())->atrasadas()->count();

        $aguardandoAnalise = (clone $visiveis())
            ->whereIn('status', [StatusSolicitacao::Enviada->value, StatusSolicitacao::EmAnalise->value])
            ->count();

        $devolvidas = Questao::query()
            ->visivelPara($usuario)
            ->where('status', StatusQuestao::Rejeitada)
            ->count();

        $extras = $devolvidas === 0 ? [] : [[
            'rotulo' => 'Devolvidas aos professores',
            'valor' => $devolvidas,
            'cor' => 'amarelo',
            'href' => route('questoes.index', ['situacao' => StatusQuestao::Rejeitada->value]),
            'detalhe' => 'Aguardando correção',
        ]];

        return [
            ...$extras,
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

        /*
         * Sempre com o professor: a parte do colega não é pendência de
         * quem já entregou a sua. Antes `whereNotNull('enviada_em')`
         * resolvia para texto e era verdadeiro em toda linha — "Já
         * enviadas" contava até as que ele nem tinha aberto.
         */
        $aResponder = (clone $minhas())
            ->where('status', StatusSolicitacao::Aberta)
            ->comPartePendente($usuario)
            ->count();

        $atrasadas = (clone $minhas())->atrasadas($usuario)->count();

        $enviadas = (clone $minhas())->comParteEnviada($usuario)->count();

        $proximoPrazo = (clone $minhas())
            ->where('status', StatusSolicitacao::Aberta)
            ->comPartePendente($usuario)
            ->orderBy('prazo')
            ->value('prazo');

        $devolvidas = $usuario->questoesDevolvidas();

        $indicadores = [];

        // O que voltou vem primeiro: é a única coisa que trava o resto do
        // fluxo esperando ação do professor.
        if ($devolvidas > 0) {
            $indicadores[] = [
                'rotulo' => 'Questões devolvidas',
                'valor' => $devolvidas,
                'cor' => 'vermelho',
                'href' => route('questoes.index', ['situacao' => StatusQuestao::Rejeitada->value]),
                'detalhe' => 'Precisam da sua correção',
            ];
        }

        return [
            ...$indicadores,
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

    protected function descricaoDoEscopo(User $usuario): string
    {
        if (! $usuario->ehGestao()) {
            return 'Área do professor';
        }

        $eixos = $usuario->eixos()->orderBy('nome')->pluck('nome');

        if ($eixos->isEmpty()) {
            return 'Nenhum Eixo vinculado à sua conta';
        }

        // Tudo nesta tela está recortado por estes Eixos, então eles são
        // a primeira coisa a dizer: sem isso, um número baixo parece erro.
        return ($eixos->count() === 1 ? 'Eixo: ' : 'Eixos: ').$eixos->join(', ');
    }
}
