# Analise Tecnica e Plano de Acao - Modulo DRE

**Data:** 03 de Marco de 2026
**Status:** Proposta Inicial
**Documento de Referencia:** Baseado nos modulos existentes de Vendas, Orcamentos e Ordens de Pagamento.

---

## 1. Analise Tecnica

### Contexto
O sistema Mercury ja gerencia o ciclo financeiro operacional (Vendas e Pagamentos) e o planejamento (Orcamentos). A implementacao de um modulo de **DRE (Demonstrativo de Resultados do Exercico)** permitira a visao gerencial consolidada, comparando o que foi planejado (Budgets) com o que foi realizado (Sales + Order Payments).

### Complexidade: Moderada a Alta
- **Alta:** Se houver necessidade de rateios complexos de despesas administrativas entre lojas.
- **Moderada:** Para consolidacao direta de receitas e despesas ja classificadas por area e loja.

### Pontos de Integracao
- **Receitas:** `adms_total_sales` (Vendas por loja/mes).
- **Despesas:** `adms_order_payments` (Pagamentos realizados).
- **Orcamento:** `adms_budgets_items` (Comparativo Previsto vs. Realizado).
- **Estrutura:** `tb_lojas`, `adms_areas` e `adms_cost_centers`.

---

## 2. Estrutura de Banco de Dados (Proposta)

Para garantir flexibilidade sem "hardcodar" as linhas do DRE, propomos uma estrutura baseada em configuracoes dinamicas.

### 2.1 Tabela: `adms_dre_lines` (Configuracao da Estrutura)
Define a hierarquia do relatorio (ex: Receita Bruta, (-) Impostos, Margem de Contribucao).

```sql
CREATE TABLE adms_dre_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    order_row INT NOT NULL,
    parent_id INT NULL,
    is_calculable TINYINT(1) DEFAULT 0, -- Se e uma linha de calculo (soma de outras)
    math_operation VARCHAR(20) NULL,    -- Ex: '+', '-', 'SUM_CHILDREN'
    status_id INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_dre_lines_parent FOREIGN KEY (parent_id) REFERENCES adms_dre_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabela: `adms_dre_mapping` (Vinculo Contabil)
Mapeia quais Centros de Custo ou Categorias de Venda alimentam cada linha.

```sql
CREATE TABLE adms_dre_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adms_dre_line_id INT NOT NULL,
    adms_cost_center_id INT NULL,
    source_type ENUM('SALES', 'EXPENSE') NOT NULL,
    
    CONSTRAINT fk_dre_map_line FOREIGN KEY (adms_dre_line_id) REFERENCES adms_dre_lines(id),
    CONSTRAINT fk_dre_map_cc FOREIGN KEY (adms_cost_center_id) REFERENCES adms_cost_centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Tabela: `adms_dre_snapshots` (Performance)
Tabela de fechamento mensal para evitar processamento pesado em tempo real.

```sql
CREATE TABLE adms_dre_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    reference_date DATE NOT NULL, -- 1º dia do mes
    adms_store_id VARCHAR(10) NULL,
    adms_area_id INT NULL,
    adms_dre_line_id INT NOT NULL,
    value_actual DECIMAL(15,2) DEFAULT 0,
    value_budget DECIMAL(15,2) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ref_store (reference_date, adms_store_id),
    CONSTRAINT fk_dre_snap_line FOREIGN KEY (adms_dre_line_id) REFERENCES adms_dre_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Escopo e Priorizacao (MoSCoW)

### Must Have (Essencial)
- [ ] Cadastro/Configuracao das linhas do DRE.
- [ ] Mapeamento de Centros de Custo -> Linhas do DRE.
- [ ] Relatorio Gerencial Realizado vs. Orcado por Loja.
- [ ] Consolidacao por Area (soma das lojas).

### Should Have (Importante)
- [ ] DRE Consolidado da Rede (Visao Direcao).
- [ ] Indicadores (EBITDA, % Margem).
- [ ] Exportacao para Excel.

### Could Have (Desejavel)
- [ ] Graficos de evolucao mensal.
- [ ] *Drill-down* (clicar no valor e ver as OPs que o compoem).
- [ ] Rateio automatico de custos administrativos centrais.

---

## 4. Cronograma Estimado (5 Semanas)

| Semana | Atividades |
|--------|------------|
| 1 | **Infraestrutura:** Tabelas, CRUD de Configuracao e Mapeamento. |
| 2 | **Engine de Calculo:** Desenvolvimento do `DREService.php` (Queries de Vendas e OPs). |
| 3 | **Integracao Budgets:** Cruzamento com `adms_budgets_items` e calculo de desvios. |
| 4 | **Frontend:** View de listagem hierarquica e filtros (Loja/Area/Periodo). |
| 5 | **Homologacao:** Validacao de saldos e ajustes de performance. |

---

## 5. Recursos e Metricas

### Recursos Necessarios
- 1 Desenvolvedor Backend (SQL + Services).
- 1 Desenvolvedor Frontend (CSS/JS para relatorios hierarquicos).
- 1 Analista Financeiro (Validacao de regras e formulas).

### Metricas de Sucesso
- **Acuracidade:** 100% de convergencia com o financeiro operacional.
- **Performance:** Tempo de geracao do relatorio < 3 segundos.
- **Engajamento:** 100% das lojas com fechamento mensal analisado via DRE.

---

## 6. Sugestoes Estrategicas

1. **Snapshot de Fechamento:** Criar funcionalidade para "congelar" o DRE de um mes apos auditoria, evitando que alteracoes retroativas mudem resultados ja apresentados.
2. **Alertas de Desvio:** Notificar gestores quando uma linha de despesa ultrapassar o orcado em mais de 10%.
3. **Comparativo YoY:** Adicionar coluna de comparacao com o mesmo mes do ano anterior (Year over Year).
4. **Rateio Proporcional:** Permitir ratear despesas de areas de apoio (ex: TI, RH) entre as lojas baseado no faturamento.

---

**Mantido por:** Equipe de Desenvolvimento - Mercury
**Versao:** 1.0 (Proposta)
