<?php

namespace App\Notifications;

use App\Models\Ausencia;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use Illuminate\Notifications\Notification;

/**
 * O que a coordenação precisa saber que aconteceu: um professor avisou
 * que vai faltar, desistiu da falta, entregou questões ou corrigiu uma
 * devolvida.
 *
 * Fica no banco e aparece no sino. O texto é montado na hora do aviso e
 * guardado pronto: se o professor mudar de nome amanhã, o aviso de hoje
 * diz o que dizia.
 */
class AvisoACoordenacao extends Notification
{
    public function __construct(
        public readonly string $assunto,
        public readonly string $titulo,
        public readonly string $mensagem,
        public readonly string $url,
    ) {}

    public static function ausenciaInformada(Ausencia $ausencia): self
    {
        return new self(
            'ausencia',
            'Ausência informada',
            "{$ausencia->professor->nome} vai faltar em {$ausencia->rotuloDoDia()}.",
            route('ausencias.show', $ausencia),
        );
    }

    public static function ausenciaCancelada(Ausencia $ausencia): self
    {
        return new self(
            'ausencia',
            'Ausência cancelada',
            "{$ausencia->professor->nome} cancelou a ausência de {$ausencia->rotuloDoDia()}.",
            route('ausencias.show', $ausencia),
        );
    }

    public static function parteEnviada(SolicitacaoParte $parte): self
    {
        $solicitacao = $parte->solicitacao;

        return new self(
            'solicitacao',
            $parte->enviada_em_atraso ? 'Questões enviadas em atraso' : 'Questões enviadas',
            "{$parte->professor->nome} enviou as questões de {$parte->disciplina->nome} "
                ."para {$solicitacao->identificacao()}.",
            route('solicitacoes.show', $solicitacao),
        );
    }

    public static function questaoReenviada(Questao $questao): self
    {
        return new self(
            'solicitacao',
            'Questão corrigida',
            "{$questao->professor->nome} reenviou uma questão de {$questao->disciplina->nome} "
                ."em {$questao->solicitacao->identificacao()}.",
            route('solicitacoes.show', $questao->solicitacao),
        );
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{assunto: string, titulo: string, mensagem: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'assunto' => $this->assunto,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'url' => $this->url,
        ];
    }
}
