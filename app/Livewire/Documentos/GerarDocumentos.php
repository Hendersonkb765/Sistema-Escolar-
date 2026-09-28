<?php

namespace App\Livewire\Documentos;

use App\Actions\Documento\GerarDocumentosAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Aluno;
use App\Models\ModeloDocumento;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Escolher o modelo, a turma e quem recebe o documento.
 *
 * A seleção começa com todos marcados porque é o caso comum — a turma
 * inteira —, e desmarcar dois é menos trabalho que marcar trinta.
 */
class GerarDocumentos extends Component
{
    use AuthorizesRequests;
    use Notifica;

    #[Url(as: 'modelo', except: null)]
    public ?int $modelo_id = null;

    #[Url(as: 'turma', except: null)]
    public ?int $turma_id = null;

    /** @var array<int, int> */
    public array $escolhidos = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ModeloDocumento::class);

        $this->modelo_id ??= $this->modelos()->first()?->id;
        $this->turma_id ??= $this->turmas()->first()?->id;

        $this->marcarTodos();
    }

    public function updatedTurmaId(): void
    {
        $this->marcarTodos();
    }

    public function marcarTodos(): void
    {
        $this->escolhidos = $this->alunos()->pluck('id')->all();
    }

    public function desmarcarTodos(): void
    {
        $this->escolhidos = [];
    }

    /** @return Collection<int, ModeloDocumento> */
    public function modelos(): Collection
    {
        return ModeloDocumento::query()
            ->visivelPara(auth()->user())
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'descricao', 'tipo', 'por_pagina']);
    }

    /** @return Collection<int, Turma> */
    public function turmas(): Collection
    {
        return Turma::query()
            ->visivelPara(auth()->user())
            ->with('curso:id,nome')
            ->orderBy('periodo_letivo', 'desc')
            ->orderBy('nome')
            ->get(['id', 'nome', 'periodo', 'periodo_letivo', 'curso_id']);
    }

    /** @return Collection<int, Aluno> */
    public function alunos(): Collection
    {
        if ($this->turma_id === null) {
            return collect();
        }

        return Aluno::query()
            ->visivelPara(auth()->user())
            ->where('turma_id', $this->turma_id)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ra', 'status', 'turma_id']);
    }

    public function modeloEscolhido(): ?ModeloDocumento
    {
        return $this->modelo_id === null
            ? null
            : $this->modelos()->firstWhere('id', $this->modelo_id);
    }

    /** Por que o botão está bloqueado, ou string vazia quando não está. */
    public function impedimento(): string
    {
        if ($this->modelos()->isEmpty()) {
            return 'Nenhum modelo de documento ativo. Crie um antes de gerar.';
        }

        if ($this->modelo_id === null) {
            return 'Escolha o modelo do documento.';
        }

        if ($this->turma_id === null) {
            return 'Escolha a turma.';
        }

        if ($this->escolhidos === []) {
            return 'Marque pelo menos um aluno.';
        }

        return '';
    }

    public function gerar(GerarDocumentosAction $acao): ?StreamedResponse
    {
        if ($this->impedimento() !== '') {
            $this->notificarAtencao($this->impedimento());

            return null;
        }

        $modelo = ModeloDocumento::query()->findOrFail($this->modelo_id);
        $turma = Turma::query()->findOrFail($this->turma_id);

        try {
            $conteudo = $acao->conteudo($modelo, $turma, $this->escolhidos, auth()->user());
        } catch (RegraDeNegocioException $erro) {
            $this->notificarErro($erro->getMessage());

            return null;
        }

        $nome = $acao->nomeDoArquivo($modelo, $turma);

        return response()->streamDownload(
            fn () => print $conteudo,
            $nome,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function render(): View
    {
        $alunos = $this->alunos();

        return view('documentos.gerar', [
            'modelos' => $this->modelos(),
            'turmas' => $this->turmas(),
            'alunos' => $alunos,
            'modelo' => $this->modeloEscolhido(),
            'impedimento' => $this->impedimento(),
            'totalEscolhidos' => count(array_intersect($this->escolhidos, $alunos->pluck('id')->all())),
        ])->layout('components.layouts.app', [
            'titulo' => 'Gerar documentos',
            'subtitulo' => 'Escolha o modelo, a turma e quais alunos recebem o documento',
        ]);
    }
}
