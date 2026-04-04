# Proposta Tecnica: Modulo de Auditoria de Estoque (Stock Audit)

**Status:** Em Implementacao (Fases 1-3 + 4B/4C concluidas)
**Versao:** 3.0
**Projeto:** Mercury
**Ultima Atualizacao:** 2026-03-12

---

## 1. Objetivo

Implementar um ecossistema completo de inventario para as lojas, permitindo auditorias programadas, aleatorias e parciais, com controle rigoroso de equipes, autorizacoes e integracao com a movimentacao historica.

---

## 2. Analise de Complexidade

| Componente | Complexidade | Esforco |
|---|---|---|
| Modelagem de dados (9 tabelas + views) | Media | Baixo |
| CRUD Ciclos (AbstractConfigController) | Baixa | Baixo |
| CRUD Fornecedores (AbstractConfigController) | Baixa | Baixo |
| CRUD Auditorias (cabecalho + equipes) | Alta | Alto |
| Fluxo de autorizacao (state machine + notificacoes) | Alta | Alto |
| Import CSV/Excel (contagens com validacao) | Alta | Medio |
| Conciliacao 3 rodadas (diff entre contagens) | Muito Alta | Alto |
| Integracao Razao/Movimentacao (analise retroativa) | Alta | Medio |
| Relatorios + PDF + E-mail | Media | Medio |
| Dashboard com alertas (vencimentos, acuracidade) | Media | Medio |
| Auditoria Aleatoria (geracao randomica por curva ABC) | Alta | Medio |
| Contagem em tempo real (leitor codigo de barras) | Media | Medio |
| Historico de acuracidade por loja | Media | Baixo |
| Testes unitarios | Media | Medio |

**Grau geral:** Alto (8/10)
**Total estimado:** ~60 arquivos novos, ~5 arquivos existentes modificados

---

## 3. Modelagem de Dados

### A. `adms_stock_audit_statuses` (Lookup de Status)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `name` | VARCHAR(50) | Nome do status |
| `color_class` | VARCHAR(20) | Classe Bootstrap (warning, success, etc.) |
| `icon` | VARCHAR(50) | Icone Font Awesome |
| `order` | INT | Ordem de exibicao |

**Seed:**
1. Rascunho (secondary, file-alt)
2. Aguardando Autorizacao (warning, hourglass-half)
3. Em Contagem (info, clipboard-check)
4. Conciliacao (primary, balance-scale)
5. Finalizada (success, check-circle)
6. Cancelada (danger, times-circle)

### B. `adms_stock_audit_cycles` (Cronograma)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `cycle_name` | VARCHAR(100) | Ex: "Ciclo Trimestral 2026" |
| `frequency` | ENUM | 'Mensal', 'Bimestral', 'Trimestral', 'Semestral' |
| `is_active` | TINYINT(1) | 1 = ativo |
| `created_at` | DATETIME | Timestamp criacao |
| `updated_at` | DATETIME | Timestamp atualizacao |

### C. `adms_stock_audits` (Cabecalho da Auditoria)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_cycle_id` | INT (FK) | Referencia `adms_stock_audit_cycles` |
| `store_id` | VARCHAR(10) (FK) | Referencia `tb_lojas.id` (ex: "Z424") |
| `audit_type` | ENUM | 'Total', 'Parcial', 'Especifica', 'Aleatoria' |
| `status_id` | INT (FK) | Referencia `adms_stock_audit_statuses` |
| `manager_responsible_id` | INT (FK) | Gerente da loja (`adms_employees`) |
| `stockist_id` | INT (FK, nullable) | Estoquista acompanhante |
| `auditor_id` | INT (FK) | Auditor responsavel (`adms_usuarios`) |
| `authorized_by` | INT (FK, nullable) | Usuario que autorizou |
| `authorized_at` | DATETIME (nullable) | Timestamp autorizacao |
| `started_at` | DATETIME (nullable) | Inicio da contagem |
| `finished_at` | DATETIME (nullable) | Finalizacao |
| `accuracy_percentage` | DECIMAL(5,2) (nullable) | Acuracidade calculada |
| `total_items_counted` | INT | Total de itens contados |
| `total_divergences` | INT | Total de divergencias |
| `financial_loss` | DECIMAL(12,2) | Valor financeiro de perdas |
| `financial_surplus` | DECIMAL(12,2) | Valor financeiro de sobras |
| `notes` | TEXT (nullable) | Observacoes gerais |
| `created_by_user_id` | INT (FK) | Criado por |
| `updated_by_user_id` | INT (FK) | Atualizado por |
| `created_at` | DATETIME | Timestamp criacao |
| `updated_at` | DATETIME | Timestamp atualizacao |

### D. `adms_stock_audit_items` (Itens da Auditoria)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `product_sku` | VARCHAR(50) | Codigo SKU do produto |
| `product_name` | VARCHAR(255) | Nome do produto (snapshot) |
| `product_barcode` | VARCHAR(50) | Codigo de barras (EAN) |
| `product_size` | VARCHAR(20) (nullable) | Tamanho do produto |
| `location` | VARCHAR(50) (nullable) | Localizacao (estoque, vitrine, deposito) |
| `system_quantity` | DECIMAL(10,2) | Quantidade no sistema (Cigam) |
| `count_1` | DECIMAL(10,2) (nullable) | 1a contagem |
| `count_1_by` | INT (FK, nullable) | Responsavel 1a contagem |
| `count_1_at` | DATETIME (nullable) | Timestamp 1a contagem |
| `count_2` | DECIMAL(10,2) (nullable) | 2a contagem |
| `count_2_by` | INT (FK, nullable) | Responsavel 2a contagem |
| `count_2_at` | DATETIME (nullable) | Timestamp 2a contagem |
| `count_3` | DECIMAL(10,2) (nullable) | 3a contagem (desempate) |
| `count_3_by` | INT (FK, nullable) | Responsavel 3a contagem |
| `count_3_at` | DATETIME (nullable) | Timestamp 3a contagem |
| `accepted_count` | DECIMAL(10,2) (nullable) | Contagem final aceita |
| `resolution_type` | ENUM | 'count_auto', 'count_manual', 'uncounted' |
| `divergence` | DECIMAL(10,2) | Diferenca (accepted_count - system_quantity) |
| `divergence_value` | DECIMAL(12,2) | Valor financeiro da divergencia (venda) |
| `divergence_value_cost` | DECIMAL(12,2) (nullable) | Valor financeiro da divergencia (custo) |
| `unit_price` | DECIMAL(12,2) | Preco unitario venda (snapshot) |
| `cost_price` | DECIMAL(12,2) (nullable) | Preco de custo (snapshot) |
| `is_justified` | TINYINT(1) DEFAULT 0 | 1 = justificado Fase B (auditor) |
| `justification_note` | TEXT (nullable) | Nota de justificativa Fase B |
| `justified_by` | INT (FK, nullable) | Usuario que justificou Fase B |
| `justified_at` | DATETIME (nullable) | Timestamp justificativa Fase B |
| `store_justified` | TINYINT(1) DEFAULT 0 | 1 = justificativa Fase C aceita |
| `store_justified_quantity` | DECIMAL(10,2) (nullable) | Qtd encontrada Fase C (parcial) |
| `observation` | TEXT (nullable) | Observacao do auditor |

### D2. `adms_stock_audit_store_justifications` (Justificativas da Loja - Fase C)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `item_id` | INT (FK) | Referencia `adms_stock_audit_items` |
| `justification_text` | TEXT | Texto da justificativa |
| `found_quantity` | DECIMAL(10,2) (nullable) | Quantidade encontrada pela loja |
| `submitted_by` | INT (FK) | Usuario que submeteu |
| `submitted_at` | DATETIME | Timestamp submissao |
| `review_status` | ENUM('pending','accepted','rejected') | Status da revisao |
| `reviewed_by` | INT (FK, nullable) | Usuario que revisou |
| `reviewed_at` | DATETIME (nullable) | Timestamp revisao |
| `review_note` | TEXT (nullable) | Nota do revisor |

### E. `adms_audit_vendors` (Empresas Terceirizadas)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `company_name` | VARCHAR(150) | Razao social |
| `cnpj` | VARCHAR(18) | CNPJ formatado |
| `contact_name` | VARCHAR(100) | Nome do contato |
| `contact_phone` | VARCHAR(20) | Telefone |
| `contact_email` | VARCHAR(100) | E-mail |
| `is_active` | TINYINT(1) | 1 = ativo |
| `created_at` | DATETIME | Timestamp criacao |
| `updated_at` | DATETIME | Timestamp atualizacao |

### F. `adms_audit_teams` (Equipe de Contagem)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `is_third_party` | TINYINT(1) | 0 = interno, 1 = terceirizado |
| `vendor_id` | INT (FK, nullable) | Referencia `adms_audit_vendors` |
| `user_id` | INT (FK, nullable) | Usuario interno (`adms_usuarios`) |
| `external_staff_name` | VARCHAR(100) (nullable) | Nome do colaborador externo |
| `external_staff_document` | VARCHAR(20) (nullable) | CPF/RG do colaborador externo |
| `role` | ENUM | 'contador', 'conferente', 'auditor', 'supervisor' |

### G. `adms_stock_audit_import_logs` (Log de Importacao)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `count_round` | TINYINT | Rodada (1, 2 ou 3) |
| `file_name` | VARCHAR(255) | Nome do arquivo importado |
| `file_path` | VARCHAR(500) | Caminho no servidor |
| `uploaded_by` | INT (FK) | Usuario que importou |
| `total_rows` | INT | Total de linhas processadas |
| `success_rows` | INT | Linhas importadas com sucesso |
| `error_rows` | INT | Linhas com erro |
| `rejected_csv_path` | VARCHAR(500) (nullable) | CSV de rejeitados |
| `created_at` | DATETIME | Timestamp |

### H. `adms_stock_audit_accuracy_history` (Historico de Acuracidade)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `store_id` | VARCHAR(10) (FK) | Referencia `tb_lojas.id` |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `accuracy_percentage` | DECIMAL(5,2) | Acuracidade da auditoria |
| `total_items` | INT | Total de itens auditados |
| `total_divergences` | INT | Total de divergencias |
| `financial_loss` | DECIMAL(12,2) | Perdas |
| `financial_surplus` | DECIMAL(12,2) | Sobras |
| `audit_date` | DATE | Data da auditoria |
| `created_at` | DATETIME | Timestamp |

### I. `adms_stock_audit_signatures` (Assinatura Digital)

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `signer_id` | INT (FK) | Usuario que assinou |
| `signer_role` | ENUM | 'gerente', 'auditor', 'supervisor' |
| `signature_data` | MEDIUMTEXT | Dados da assinatura (base64 canvas) |
| `ip_address` | VARCHAR(45) | IP do dispositivo |
| `user_agent` | VARCHAR(500) | Browser/dispositivo |
| `signed_at` | DATETIME | Timestamp da assinatura |

---

## 4. Funcionalidades

### A. Gestao de Equipes (Internas e Terceirizadas)

O sistema permite a composicao mista de equipes. Para auditores terceirizados, o sistema registra a empresa contratada e o nome dos conferentes, garantindo que em cada arquivo importado seja possivel rastrear qual empresa ou pessoa foi responsavel por aquela contagem especifica. Isso e vital para auditoria de contratos de inventario externo.

### B. Fluxo de Autorizacao (State Machine)

Nenhuma auditoria pode ser iniciada sem **Autorizacao Digital**.

```
Rascunho → Aguardando Autorizacao → Em Contagem → Conciliacao (Fase B) → Justificativas Loja (Fase C) → Finalizada
                                                                                                            ↓
                                                                                                       Cancelada
```

**Transicoes:**
- `Rascunho → Aguardando Autorizacao`: Auditor solicita inicio
- `Aguardando Autorizacao → Em Contagem`: Nivel superior aprova (notificacao WebSocket)
- `Em Contagem → Conciliacao`: Todas as contagens importadas
- `Conciliacao → Justificativas Loja`: Se houver divergencias nao justificadas na Fase B
- `Conciliacao → Finalizada`: Se todas as divergencias foram justificadas na Fase B
- `Justificativas Loja → Finalizada`: Apos revisao de todas as justificativas (ou finalizacao manual)
- `Qualquer → Cancelada`: Apenas nivel superior pode cancelar

**Fase C (Justificativas da Loja):**
A loja submete justificativas para itens divergentes. O revisor (nivel superior) aceita ou rejeita cada justificativa. Logica assimetrica:
- **Perda aceita** → deduz do resultado (perda explicada)
- **Perda rejeitada** → permanece como perda
- **Sobra aceita** → permanece no resultado (estoque real, precisa ajuste)
- **Sobra rejeitada** → deduz do resultado (erro de contagem)

Cada transicao gera: log (LoggerService), notificacao (WebSocket + e-mail), registro de auditoria.

**Service:** `AuditStateMachineService` centraliza toda logica de transicao e validacao.

### C. Auditorias Aleatorias (Auto-Auditoria)

Para lojas sem equipe dedicada de auditoria, o sistema gera listas randomicas de SKUs (baseado em curva ABC ou produtos com alta movimentacao) para contagem diaria rapida pelo gerente da loja, garantindo a acuracia sem a necessidade de um inventario total.

### D. Cronograma e Ciclos

Interface para o RH/Auditoria definir o calendario anual.
- **Exemplo:** Loja A (Ciclo Mensal), Loja B (Ciclo Trimestral).
- O sistema sinaliza automaticamente auditorias proximas do vencimento no dashboard.

### E. Contagem em Tempo Real

Alem da importacao CSV, o sistema oferece interface web onde o estoquista escaneia com leitor de codigo de barras USB/Bluetooth e o sistema registra a contagem em tempo real. Mais pratico que gerar CSV e depois importar.

### F. Comparacao Automatica com Saldo Cigam

O campo `system_quantity` e preenchido automaticamente via sync com PostgreSQL (Cigam) no momento da abertura da auditoria. Nao e necessario digitacao manual.

---

## 5. Coleta e Processamento de Dados

### A. Importacao de Arquivos de Contagem

- Suporte para importacao via **CSV/Excel** gerados por coletores externos
- **Download de Modelo:** Arquivo `.csv` modelo com cabecalhos corretos
- **Log de Importacao:** Registro completo em `adms_stock_audit_import_logs`
- **CSV de Rejeitados:** Linhas com erro salvas em `uploads/import_errors/` (padrao Products)

### B. Multiplas Contagens (Conciliacao)

O sistema suporta ate 3 rodadas de contagem:

1. **1a Contagem:** Equipe principal realiza contagem completa
2. **2a Contagem:** Segunda equipe (ou mesma) realiza recontagem
3. **3a Contagem (Desempate):** Apenas para itens divergentes entre 1a e 2a. O Auditor Responsavel (`auditor_id`) realiza a contagem final

**Logica de conciliacao:**
- Se `count_1 == count_2`: `final_quantity = count_1` (aceito automaticamente)
- Se `count_1 != count_2`: item marcado para 3a contagem
- Se 3a contagem realizada: `final_quantity = count_3` (prevalece desempate)
- `divergence = final_quantity - system_quantity`
- `divergence_value = divergence * unit_price`

---

## 6. Integracao com Razao e Movimentacao

- **Analise Retroativa:** O relatorio de divergencia permite selecionar um "Periodo de Razao" (ex: "Mostrar movimentacao deste produto nos ultimos 30 dias")
- Isso ajuda a identificar se o erro foi uma venda nao baixada, uma transferencia nao confirmada ou um erro de recebimento
- Consulta cruza dados MySQL (Mercury) + PostgreSQL (Cigam) via `AdmsReadCigam`

---

## 7. Notificacoes e Encerramento

### Relatorios por E-mail

Ao finalizar a auditoria, o sistema envia automaticamente para o Gerente, Supervisor e Auditor:
- Resumo de Acuracidade (%)
- Total Financeiro de Perdas e Sobras
- Top 10 Produtos com maior divergencia
- PDF detalhado em anexo (DomPDF)

### Historico de Acuracidade

Cada auditoria finalizada registra automaticamente em `adms_stock_audit_accuracy_history`, permitindo:
- Grafico de evolucao por loja ao longo do tempo
- Comparativo entre lojas
- Identificacao de tendencias de melhoria ou piora

---

## 8. Permissoes e Niveis de Acesso

| Acao | Nivel 1 (Super) | Nivel 2 (Admin) | Nivel 3 (Suporte) | Nivel 5 (Gerente) | Nivel 18 (Loja) |
|---|---|---|---|---|---|
| Criar auditoria | Sim | Sim | Sim | Nao | Nao |
| Autorizar auditoria | Sim | Sim | Nao | Nao | Nao |
| Executar contagem | Sim | Sim | Sim | Sim | Sim |
| Importar CSV | Sim | Sim | Sim | Sim | Nao |
| Conciliar | Sim | Sim | Sim | Nao | Nao |
| Finalizar | Sim | Sim | Sim | Nao | Nao |
| Cancelar | Sim | Sim | Nao | Nao | Nao |
| Ver relatorios | Sim | Sim | Sim | Sim | Nao |
| Assinar digitalmente | Sim | Sim | Sim | Sim | Nao |
| Gerenciar ciclos | Sim | Sim | Nao | Nao | Nao |
| Gerenciar fornecedores | Sim | Sim | Nao | Nao | Nao |

---

## 9. Inovacoes (v2.0)

1. **Mapa de Calor da Loja:** Visualizacao das areas (estoque vs vitrine) com maior indice de erros para treinamento da equipe
2. **Assinatura Digital:** Ao fim da auditoria, Gerente e Auditor assinam digitalmente (via tablet/celular) o termo de encerramento. Dados armazenados em `adms_stock_audit_signatures`
