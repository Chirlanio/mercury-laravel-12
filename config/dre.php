<?php

/**
 * Configuração do módulo DRE.
 *
 * `default_sale_account_code` é o code de `chart_of_accounts` usado pelo
 * `SaleToDreProjector` quando a loja da venda não tem `sale_chart_of_account_id`
 * configurado. Deve ser uma conta analítica de grupo 3 (Receitas). Ver
 * `docs/dre-arquitetura.md §12.4 #17` (resposta: conta por loja com fallback).
 *
 * Se a config estiver vazia OU a conta não existir no plano, o projetor faz
 * SKIP (com log warning) em vez de quebrar a criação da Sale. Essa tolerância
 * é deliberada: a DRE é um serviço secundário em relação ao fluxo operacional
 * de venda.
 */
return [
    /**
     * Code da conta contábil analítica (grupo 3 — Receitas) que recebe
     * projeções de Sales quando a loja não tem conta própria configurada.
     *
     * Override via env DRE_DEFAULT_SALE_ACCOUNT_CODE. Se null/não-existente,
     * SaleToDreProjector faz skip com log warning.
     */
    'default_sale_account_code' => env('DRE_DEFAULT_SALE_ACCOUNT_CODE', '3.1.1.01.00012'),
];
