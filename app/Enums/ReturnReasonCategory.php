<?php

namespace App\Enums;

/**
 * Categoria do motivo da devolução. Cada motivo cadastrado em
 * return_reasons pertence a uma destas categorias (enum fixo).
 *
 * Serve para análise agregada (dashboard) e para o frontend filtrar
 * motivos por categoria em cascata.
 *
 *  - arrependimento: cliente mudou de ideia (CDC 7 dias úteis — o
 *    módulo não calcula prazo automaticamente, fica como observação).
 *  - defeito: produto com defeito de qualidade.
 *  - divergencia: produto diferente do anúncio.
 *  - tamanho_cor: cliente errou tamanho/cor na compra.
 *  - nao_recebido: entrega extraviada ou não chegou ao cliente.
 *  - outro: fallback para casos fora das categorias acima.
 */
enum ReturnReasonCategory: string
{
    case ARREPENDIMENTO = 'arrependimento';
    case DEFEITO = 'defeito';
    case DIVERGENCIA = 'divergencia';
    case TAMANHO_COR = 'tamanho_cor';
    case NAO_RECEBIDO = 'nao_recebido';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::ARREPENDIMENTO => 'Arrependimento',
            self::DEFEITO => 'Defeito / Qualidade',
            self::DIVERGENCIA => 'Divergência do anúncio',
            self::TAMANHO_COR => 'Tamanho / Cor errados',
            self::NAO_RECEBIDO => 'Não recebido',
            self::OUTRO => 'Outro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ARREPENDIMENTO => 'info',
            self::DEFEITO => 'danger',
            self::DIVERGENCIA => 'warning',
            self::TAMANHO_COR => 'purple',
            self::NAO_RECEBIDO => 'orange',
            self::OUTRO => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->color()])
            ->all();
    }
}
