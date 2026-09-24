<?php

namespace App\Livewire\ModelosProva;

use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\NormaDaFolha;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Support\LayoutDaFolha;
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

    // --- Formatação da folha ---------------------------------------------

    public string $norma = 'abnt';

    public string $fonte = 'sans';

    public int $tamanho = 12;

    public float $espacamento = 1.5;

    public float $margem_superior = 30;

    public float $margem_inferior = 20;

    public float $margem_esquerda = 30;

    public float $margem_direita = 20;

    // --- Logos ------------------------------------------------------------

    public ?TemporaryUploadedFile $logoEsquerda = null;

    public ?TemporaryUploadedFile $logoDireita = null;

    public bool $removerLogoEsquerda = false;

    public bool $removerLogoDireita = false;

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

            $this->carregarLayout($modelo->layoutDaFolha());

            return;
        }

        $this->authorize('create', ModeloProva::class);

        $this->eixo_id = auth()->user()->eixoIds()[0] ?? null;
        $this->instituicao = (string) config('app.name');

        $this->carregarLayout(LayoutDaFolha::de([]));
    }

    protected function carregarLayout(LayoutDaFolha $layout): void
    {
        $this->norma = $layout->norma->value;
        $this->fonte = $layout->fonte;
        $this->tamanho = $layout->tamanho;
        $this->espacamento = $layout->espacamento;
        $this->margem_superior = $layout->margens['superior'];
        $this->margem_inferior = $layout->margens['inferior'];
        $this->margem_esquerda = $layout->margens['esquerda'];
        $this->margem_direita = $layout->margens['direita'];
    }

    /**
     * Trocar para ABNT devolve os campos aos valores da norma — senão a
     * tela mostraria 11 pt travado enquanto a folha imprime 12.
     */
    public function updatedNorma(): void
    {
        $this->carregarLayout($this->layoutEscolhido());
    }

    public function normaEscolhida(): NormaDaFolha
    {
        return NormaDaFolha::tryFrom($this->norma) ?? NormaDaFolha::Abnt;
    }

    protected function layoutEscolhido(): LayoutDaFolha
    {
        return LayoutDaFolha::de([
            'norma' => $this->norma,
            'fonte' => $this->fonte,
            'tamanho' => $this->tamanho,
            'espacamento' => $this->espacamento,
            'margens' => [
                'superior' => $this->margem_superior,
                'inferior' => $this->margem_inferior,
                'esquerda' => $this->margem_esquerda,
                'direita' => $this->margem_direita,
            ],
        ]);
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
            'norma' => ['required', Rule::in(NormaDaFolha::valores())],
            'fonte' => ['required', Rule::in(array_keys(LayoutDaFolha::FONTES))],
            'tamanho' => ['required', 'integer', 'min:8', 'max:16'],
            'espacamento' => ['required', 'numeric', 'min:1', 'max:2'],
            'margem_superior' => ['required', 'numeric', 'min:5', 'max:50'],
            'margem_inferior' => ['required', 'numeric', 'min:5', 'max:50'],
            'margem_esquerda' => ['required', 'numeric', 'min:5', 'max:50'],
            'margem_direita' => ['required', 'numeric', 'min:5', 'max:50'],
            'logoEsquerda' => ['nullable', 'image', 'max:2048'],
            'logoDireita' => ['nullable', 'image', 'max:2048'],
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
            'norma' => 'norma de formatação',
            'tamanho' => 'tamanho da fonte',
            'espacamento' => 'espaçamento entre linhas',
            'margem_superior' => 'margem superior',
            'margem_inferior' => 'margem inferior',
            'margem_esquerda' => 'margem esquerda',
            'margem_direita' => 'margem direita',
            'logoEsquerda' => 'logo da esquerda',
            'logoDireita' => 'logo da direita',
        ];
    }

    public function salvar(): void
    {
        $this->modelo !== null
            ? $this->authorize('update', $this->modelo)
            : $this->authorize('create', ModeloProva::class);

        $this->validate();

        $dados = [
            'eixo_id' => $this->eixo_id,
            'nome' => $this->nome,
            'instituicao' => $this->instituicao,
            'nome_avaliacao' => $this->nome_avaliacao,
            'cabecalho' => $this->cabecalho,
            'instrucoes' => $this->instrucoes,
            'rodape' => $this->rodape,
            'campos_identificacao' => array_values($this->campos_identificacao),
            'ativo' => $this->ativo,
            // Sob a ABNT, o que vai para o banco são os valores da norma,
            // não os que estavam no formulário antes de ela ser marcada.
            'layout' => $this->layoutEscolhido()->paraJson(),
        ];

        $dados += $this->caminhoDasLogos();

        if ($this->modelo === null) {
            $dados['criado_por'] = auth()->id();

            ModeloProva::create($dados);
        } else {
            $this->modelo->update($dados);
        }

        session()->flash('sucesso', 'Modelo de prova salvo com sucesso.');

        $this->redirectRoute('modelos-prova.index', navigate: true);
    }

    /** @return array<string, ?string> */
    protected function caminhoDasLogos(): array
    {
        $caminhos = [];

        foreach ([
            'logo_esquerda_path' => ['logoEsquerda', 'removerLogoEsquerda'],
            'logo_direita_path' => ['logoDireita', 'removerLogoDireita'],
        ] as $coluna => [$arquivo, $remover]) {
            if ($this->{$arquivo} !== null) {
                $caminhos[$coluna] = $this->{$arquivo}->store('modelos-prova', 'public');
            } elseif ($this->{$remover}) {
                $caminhos[$coluna] = null;
            }
        }

        return $caminhos;
    }

    public function render(RenderizarProvaAction $renderizar): View
    {
        return view('modelos-prova.formulario', [
            'eixos' => Eixo::query()->whereIn('id', auth()->user()->eixoIds())->orderBy('nome')->get(),
            'camposDisponiveis' => ModeloProva::CAMPOS_DE_IDENTIFICACAO,
            'fontesDisponiveis' => LayoutDaFolha::FONTES,
            'normas' => NormaDaFolha::opcoes(),
            'normaEscolhida' => $this->normaEscolhida(),
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
            'layout' => $this->layoutEscolhido()->paraJson(),
        ]);

        // A amostra mostra a logo que já está guardada; a recém-enviada
        // ainda é um arquivo temporário, fora do disco público.
        if ($this->removerLogoEsquerda) {
            $amostra->logo_esquerda_path = null;
        }

        if ($this->removerLogoDireita) {
            $amostra->logo_direita_path = null;
        }

        return $amostra;
    }
}
