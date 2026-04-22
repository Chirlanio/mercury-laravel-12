<?php

namespace App\Services\DRE\Contracts;

/**
 * Abstração do leitor de períodos fechados.
 *
 * `DreMatrixService` delega para uma implementação desta interface quando
 * o filtro de período atravessa meses que têm snapshot em
 * `dre_period_closing_snapshots`. Implementação real é entregue no prompt 11
 * do playbook (arquitetura §2.8). Por ora `NullClosedPeriodReader` devolve
 * sempre vazio — matriz é sempre computada live.
 */
interface ClosedPeriodReader
{
    /**
     * Lista os year_months 'YYYY-MM' dentro do intervalo do filtro que têm
     * snapshot imutável e devem ser lidos do snapshot em vez da matriz live.
     *
     * @param  array{start_date:string,end_date:string,scope:string}  $filter
     * @return array<int,string>  Ex: ['2026-01','2026-02']
     */
    public function closedYearMonths(array $filter): array;

    /**
     * Retorna linhas de snapshot keyed por year_month → management_line_id
     * → ['actual' => float, 'budget' => float, 'previous_year' => float].
     *
     * @param  array{start_date:string,end_date:string,scope:string,store_ids?:array,network_ids?:array,budget_version?:string}  $filter
     * @return array<string,array<int,array{actual:float,budget:float,previous_year:float}>>
     */
    public function readSnapshot(array $filter): array;
}
