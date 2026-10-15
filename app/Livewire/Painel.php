<?php

namespace App\Livewire;

use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
            // O número diz quantos; a lista diz quais, e é com ela que
            // se faz alguma coisa sem passar por três telas antes.
            'pendencias' => $this->pendencias($usuario),
            'estrutura' => $usuario->ehGestao() ? $this->estrutura($usuario) : [],
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

        return $this->indicadoresDeAvaliacao($usuario);
    }

    /**
     * A estrutura acadêmica: referência, não pendência.
     *
     * Ficava na mesma fileira das pendências, e "Disciplinas: 8" não
     * compete por atenção com "3 solicitações atrasadas" — só empurra a
     * segunda para longe do olho. Desce para o pé da página, menor.
     *
     * @return array<int, array{rotulo: string, valor: int|string, href: ?string}>
     */
    protected function estrutura(User $usuario): array
    {
        /*
         * "Eixos no meu escopo: 1" não informa nada a quem tem um Eixo
         * só — e é o caso do PAEET. O subtítulo já diz qual é. O item
         * fica para quem tem mais de um, onde o número é notícia.
         */
        $itens = count($usuario->eixoIds()) > 1 ? [
            [
                'rotulo' => 'Eixos no meu escopo',
                'valor' => count($usuario->eixoIds()),
                'href' => Gate::allows('viewAny', Eixo::class) ? route('eixos.index') : null,
            ],
        ] : [];

        $itens = [
            ...$itens,
            [
                'rotulo' => 'Cursos',
                'valor' => Curso::query()->visivelPara($usuario)->count(),
                'href' => route('cursos.index'),
            ],
            [
                'rotulo' => 'Disciplinas',
                'valor' => Disciplina::query()->visivelPara($usuario)->count(),
                'href' => route('disciplinas.index'),
            ],
            [
                'rotulo' => 'Turmas ativas',
                'valor' => Turma::query()->visivelPara($usuario)->where('status', 'ativa')->count(),
                'href' => route('turmas.index'),
            ],
            [
                'rotulo' => 'Alunos',
                'valor' => Aluno::query()->visivelPara($usuario)->count(),
                'href' => route('alunos.index'),
            ],
        ];

        if (Gate::allows('viewAny', User::class)) {
            $itens[] = [
                'rotulo' => 'Usuários',
                // Pelo mesmo escopo da tela de usuários, que é para
                // onde este número leva: se divergirem, o painel mente.
                'valor' => User::query()->visivelPara($usuario)->count(),
                'href' => route('usuarios.index'),
            ];
        }

        return $itens;
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

        /*
         * O ciclo não termina na análise das questões: a prova é
         * montada, aplicada, e a folha é importada. As duas pontas
         * seguintes não apareciam em lugar nenhum — uma prova aplicada
         * em abril podia passar o ano sem resultado, e ninguém era
         * avisado.
         */
        $semResultado = Prova::query()
            ->visivelPara($usuario)
            ->whereIn('status', [StatusProva::Gerada->value, StatusProva::Aplicada->value])
            ->whereDoesntHave('resultados')
            ->count();

        // Conferida e abandonada: o relatório existe e ninguém confirmou.
        $aConfirmar = Importacao::query()
            ->visivelPara($usuario)
            ->where('status', StatusImportacao::Validada)
            ->count();

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
            [
                'rotulo' => 'Provas sem resultado',
                'valor' => $semResultado,
                'cor' => $semResultado > 0 ? 'amarelo' : 'verde',
                // Para a importação, que é o que se faz com elas — e não
                // para a lista de provas, que não sabe filtrar por isto.
                'href' => route('importacoes.criar'),
                'detalhe' => 'Geradas ou aplicadas, sem folha importada',
            ],
            [
                'rotulo' => 'Importações a confirmar',
                'valor' => $aConfirmar,
                'cor' => $aConfirmar > 0 ? 'amarelo' : 'cinza',
                'href' => route('importacoes.index', ['situacao' => StatusImportacao::Validada->value]),
                'detalhe' => 'Conferidas, ainda não gravadas',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | O que precisa de você
    |--------------------------------------------------------------------------
    |
    | O número diz quantos; a lista diz quais. Sem ela, "3 atrasadas"
    | custa três telas até chegar na primeira — e o painel vira um lugar
    | por onde se passa, não onde se começa.
    */

    /** @return Collection<int, array<string, mixed>> */
    protected function pendencias(User $usuario): Collection
    {
        return $usuario->ehGestao()
            ? $this->pendenciasDaGestao($usuario)
            : $this->pendenciasDoProfessor($usuario);
    }

    /**
     * As solicitações que esperam alguém, da mais urgente para a menos.
     *
     * Atrasadas primeiro, depois as de prazo mais próximo. Quem falta
     * vai junto: "esperando o professor X" é o que permite agir sem
     * abrir a solicitação para descobrir.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function pendenciasDaGestao(User $usuario): Collection
    {
        return SolicitacaoProva::query()
            ->visivelPara($usuario)
            ->comPartePendente()
            ->whereIn('status', [
                StatusSolicitacao::Aberta->value,
                StatusSolicitacao::Enviada->value,
                StatusSolicitacao::EmAnalise->value,
            ])
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->withCount(['partes as partes_pendentes' => fn ($q) => $q->whereNull('enviada_em')])
            ->orderBy('prazo')
            ->limit(5)
            ->get()
            ->map(fn (SolicitacaoProva $solicitacao) => [
                'id' => $solicitacao->getKey(),
                'titulo' => $solicitacao->titulo ?: 'Solicitação sem título',
                'turma' => $solicitacao->turma->nome.' · '.$solicitacao->turma->curso->nome,
                'prazo' => $solicitacao->prazo,
                'atrasada' => $solicitacao->prazo->isPast(),
                'faltam' => (int) $solicitacao->partes_pendentes,
                'href' => route('solicitacoes.show', $solicitacao),
            ]);
    }

    /**
     * O que foi pedido ao professor e ele ainda não enviou.
     *
     * Só a parte dele: a do colega não é pendência de quem já entregou.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function pendenciasDoProfessor(User $usuario): Collection
    {
        return SolicitacaoParte::query()
            ->whereNull('enviada_em')
            ->where('professor_id', $usuario->getKey())
            ->whereHas('solicitacao', fn ($q) => $q
                ->visivelPara($usuario)
                ->where('status', StatusSolicitacao::Aberta))
            ->with([
                'disciplina:id,nome',
                // `quantidade_alternativas` entra porque
                // `questoesCompletas()` a lê para saber o que é uma
                // questão pronta — sem ela, o modo estrito reclama.
                'solicitacao:id,titulo,prazo,turma_id,quantidade_alternativas',
                'solicitacao.turma:id,nome,curso_id',
                'solicitacao.turma.curso:id,nome,eixo_id',
            ])
            ->get()
            ->sortBy(fn (SolicitacaoParte $parte) => $parte->solicitacao->prazo)
            ->take(5)
            ->values()
            ->map(fn (SolicitacaoParte $parte) => [
                'id' => $parte->getKey(),
                'titulo' => $parte->disciplina->nome,
                'turma' => $parte->solicitacao->turma->nome
                    .' · '.$parte->solicitacao->turma->curso->nome,
                'prazo' => $parte->solicitacao->prazo,
                'atrasada' => $parte->solicitacao->prazo->isPast(),
                // Quantas ainda faltam para a parte ficar completa.
                'faltam' => max(0, $parte->quantidade_questoes - $parte->questoesCompletas()),
                'href' => route('solicitacoes.responder', $parte->solicitacao),
            ]);
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
