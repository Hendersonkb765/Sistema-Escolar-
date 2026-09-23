<?php

namespace App\Livewire\ModelosProva;

use App\Actions\Prova\RenderizarProvaAction;
use App\Models\Eixo;
use App\Models\ModeloProva;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * O modelo é a moldura da folha: o que sai antes e depois das questões.
 *
 * Ele não guarda questão nenhuma. Corrigir aqui o nome da instituição ou
 * o rodapé vale também para a reimpressão de provas já montadas — é o que
 * se quer de um papel timbrado. O conteúdo da prova, esse sim, está
 * congelado no snapshot dela e não muda.
 */
class FormularioModeloProva extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?ModeloProva $modelo = null;

    public ?int $eixo_id = null;

    public string $nome = '';

    public string $instituicao = '';

    public string $nome_avaliacao = '';

    public string $cabecalho = '';

    public string $instrucoes = '';

    public string $rodape = '';

    /** @var array<int, string> */
    public array $campos_identificacao = ['aluno', 'matricula', 'turma', 'data'];

    public bool $ativo = true;

    public ?TemporaryUploadedFile $logo = null;

    public bool $removerLogo = false;

    public function mount(?ModeloProva $modelo = null): void
    {
        if ($modelo?->exists) {
            $this->authorize('update', $modelo);

            $this->modelo = $modelo;
            $this->eixo_id = $modelo->eixo_id;
            $this->nome = $modelo->nome;
            $this->instituicao = (string) $modelo->instituicao;
            $this->nome_avaliacao = (string) $modelo->nome_avaliacao;
            $this->cabecalho = (string) $modelo->cabecalho;
            $this->instrucoes = (string) $modelo->instrucoes;
            $this->rodape = (string) $modelo->rodape;
            $this->campos_identificacao = $modelo->campos_identificacao ?? [];
            $this->ativo = $modelo->ativo;

            return;
        }

        $this->authorize('create', ModeloProva::class);

        $this->eixo_id = auth()->user()->eixoIds()[0] ?? null;
        $this->instituicao = (string) config('app.name');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'eixo_id' => ['required', Rule::in(auth()->user()->eixoIds())],
            'nome' => ['required', 'string', 'max:255'],
            'instituicao' => ['nullable', 'string', 'max:255'],
            'nome_avaliacao' => ['nullable', 'string', 'max:255'],
            'cabecalho' => ['nullable', 'string', 'max:1000'],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
            'rodape' => ['nullable', 'string', 'max:500'],
            'campos_identificacao' => ['array'],
            'campos_identificacao.*' => [Rule::in(array_keys(ModeloProva::CAMPOS_DE_IDENTIFICACAO))],
            'ativo' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'eixo_id' => 'eixo',
            'nome_avaliacao' => 'nome da avaliação',
            'cabecalho' => 'cabeçalho',
            'instrucoes' => 'instruções',
            'rodape' => 'rodapé',
            'campos_identificacao' => 'campos de identificação',
        ];
    }

    public function salvar(): void
    {
        $this->modelo !== null
            ? $this->authorize('update', $this->modelo)
            : $this->authorize('create', ModeloProva::class);

        $dados = $this->validate();
        unset($dados['logo']);

        $dados['campos_identificacao'] = array_values($this->campos_identificacao);

        if ($this->logo !== null) {
            $dados['logo_path'] = $this->logo->store('modelos-prova', 'public');
        } elseif ($this->removerLogo) {
            $dados['logo_path'] = null;
        }

        if ($this->modelo === null) {
            $dados['criado_por'] = auth()->id();

            ModeloProva::create($dados);
        } else {
            $this->modelo->update($dados);
        }

        session()->flash('sucesso', 'Modelo de prova salvo com sucesso.');

        $this->redirectRoute('modelos-prova.index', navigate: true);
    }

    public function render(RenderizarProvaAction $renderizar): View
    {
        return view('modelos-prova.formulario', [
            'eixos' => Eixo::query()->whereIn('id', auth()->user()->eixoIds())->orderBy('nome')->get(),
            'camposDisponiveis' => ModeloProva::CAMPOS_DE_IDENTIFICACAO,
            'folha' => $renderizar->amostraDoModelo($this->paraAmostra()),
        ])->layout('components.layouts.app', [
            'titulo' => $this->modelo === null ? 'Novo modelo de prova' : 'Editar modelo de prova',
            'subtitulo' => $this->modelo?->nome,
        ]);
    }

    /** Modelo não persistido, só para desenhar a amostra do cabeçalho. */
    protected function paraAmostra(): ModeloProva
    {
        $amostra = $this->modelo === null ? new ModeloProva : clone $this->modelo;

        $amostra->fill([
            'nome' => $this->nome ?: 'Modelo sem nome',
            'instituicao' => $this->instituicao,
            'nome_avaliacao' => $this->nome_avaliacao,
            'cabecalho' => $this->cabecalho,
            'instrucoes' => $this->instrucoes,
            'rodape' => $this->rodape,
            'campos_identificacao' => array_values($this->campos_identificacao),
        ]);

        return $amostra;
    }
}
