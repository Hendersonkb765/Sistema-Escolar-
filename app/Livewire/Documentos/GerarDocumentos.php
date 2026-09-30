<?php

namespace App\Livewire\Documentos;

use App\Actions\Documento\GerarDocumentosAction;
use App\Actions\Documento\GerarDocxDoDocumentoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Aluno;
use App\Models\ModeloDocumento;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Filtra a lista por nome ou RA.
     *
     * Numa turma de quarenta e quatro, achar um aluno rolando a página é
     * o trabalho que esta tela deveria poupar. A busca **não** desmarca
     * ninguém: ela esconde linhas, e quem estava marcado continua
     * marcado — senão procurar um aluno desfaria a seleção dos outros.
     */
    public string $busca = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ModeloDocumento::class);

        $this->modelo_id ??= $this->modelos()->first()?->id;
        $this->turma_id ??= $this->turmas()->first()?->id;

        $this->marcarTodos();
    }

    public function updatedTurmaId(): void
    {
        $this->busca = '';
        $this->escolhidos = [];
        $this->marcarTodos();
    }

    /**
     * Marcar e desmarcar valem para o que está à vista.
     *
     * Com a busca preenchida, "marcar todos" no sentido literal marcaria
     * gente que a pessoa não está vendo. O rótulo do botão diz quantos
     * são, para não haver dúvida.
     */
    public function marcarTodos(): void
    {
        $this->escolhidos = array_values(array_unique(array_merge(
            $this->escolhidos,
            $this->alunos()->pluck('id')->all(),
        )));
    }

    public function desmarcarTodos(): void
    {
        $visiveis = $this->alunos()->pluck('id')->all();

        $this->escolhidos = array_values(array_diff($this->escolhidos, $visiveis));
    }

    public function limparBusca(): void
    {
        $this->busca = '';
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

    /**
     * Os alunos à vista: os da turma, passados pela busca.
     *
     * @return Collection<int, Aluno>
     */
    public function alunos(): Collection
    {
        return $this->daTurma()->when($this->busca !== '', function ($consulta) {
            $termo = '%'.str_replace('%', '\%', trim($this->busca)).'%';

            // Nome ou RA: quem tem a lista de chamada na mão procura pelo
            // número, e quem tem o bilhete na mão procura pelo nome.
            $consulta->where(fn ($sub) => $sub
                ->where('nome', 'like', $termo)
                ->orWhere('ra', 'like', $termo));
        })->get(['id', 'nome', 'ra', 'status', 'turma_id']);
    }

    /** @return Collection<int, Aluno> */
    public function todosDaTurma(): Collection
    {
        return $this->daTurma()->get(['id', 'nome', 'ra', 'status', 'turma_id']);
    }

    protected function daTurma(): Builder
    {
        if ($this->turma_id === null) {
            // Consulta que não devolve nada, para o resto do método não
            // precisar saber que a turma pode faltar.
            return Aluno::query()->whereRaw('1 = 0');
        }

        return Aluno::query()
            ->visivelPara(auth()->user())
            ->where('turma_id', $this->turma_id)
            ->orderBy('nome');
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

        if (array_intersect($this->escolhidos, $this->todosDaTurma()->pluck('id')->all()) === []) {
            return 'Marque pelo menos um aluno.';
        }

        return '';
    }

    /**
     * O PDF serve para imprimir; o Word, para mexer antes de imprimir —
     * trocar uma data, acrescentar um parágrafo. A escolha é de quem gera,
     * na hora, e não uma propriedade do modelo.
     */
    public function gerar(
        string $formato,
        GerarDocumentosAction $emPdf,
        GerarDocxDoDocumentoAction $emWord,
    ): ?StreamedResponse {
        if (! in_array($formato, ['pdf', 'word'], true)) {
            return null;
        }

        if ($this->impedimento() !== '') {
            $this->notificarAtencao($this->impedimento());

            return null;
        }

        $modelo = ModeloDocumento::query()->findOrFail($this->modelo_id);
        $turma = Turma::query()->findOrFail($this->turma_id);

        $acao = $formato === 'word' ? $emWord : $emPdf;

        try {
            $conteudo = $acao->conteudo($modelo, $turma, $this->escolhidos, auth()->user());
        } catch (RegraDeNegocioException $erro) {
            $this->notificarErro($erro->getMessage());

            return null;
        }

        return response()->streamDownload(
            fn () => print $conteudo,
            $acao->nomeDoArquivo($modelo, $turma),
            ['Content-Type' => $formato === 'word'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf'],
        );
    }

    public function render(): View
    {
        $visiveis = $this->alunos();
        $daTurma = $this->todosDaTurma();

        return view('documentos.gerar', [
            'modelos' => $this->modelos(),
            'turmas' => $this->turmas(),
            'alunos' => $visiveis,
            'totalDaTurma' => $daTurma->count(),
            'modelo' => $this->modeloEscolhido(),
            'impedimento' => $this->impedimento(),
            // O que vale é o que está marcado na turma, e não só o que a
            // busca deixou à vista: gerar leva todos os marcados.
            'totalEscolhidos' => count(array_intersect($this->escolhidos, $daTurma->pluck('id')->all())),
            'visiveisMarcados' => count(array_intersect($this->escolhidos, $visiveis->pluck('id')->all())),
        ])->layout('components.layouts.app', [
            'titulo' => 'Gerar documentos',
            'subtitulo' => 'Escolha o modelo, a turma e quais alunos recebem o documento',
        ]);
    }
}
