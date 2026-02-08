# Mercury - Documentacao do Sistema

**Versao:** 2.2.0
**Ultima Atualizacao:** 07 de Fevereiro de 2026

---

## Estrutura da Documentacao

```
docs/
├── README.md                        # Este arquivo (indice geral)
├── SETUP_ENVIRONMENT.md             # Configuracao do ambiente
├── GUIA_IMPLEMENTACAO_MODULOS.md    # Guia para criar novos modulos
├── PADRONIZACAO.md                  # Templates de codigo
├── DELETE_MODAL_IMPLEMENTATION_GUIDE.md  # Padrao de modals de exclusao
├── CHECKLIST_MODULOS.md             # Checklist de implementacao
├── MERCURY_SYSTEM_DOCUMENTATION.md  # Documentacao geral do sistema
├── CHANGELOG_ORDEM_SERVICO.md       # Historico de alteracoes - Ordem de Servico
│
├── modules/                         # Documentacao por modulo
│   ├── MODULO_BUDGET.md             # Orcamentos
│   ├── MODULO_CHECKLIST.md          # Checklists de loja
│   ├── MODULO_CHAT.md               # Chat interno
│   ├── MODULO_LOGGER.md             # Sistema de logs
│   ├── MODULO_EMPLOYEES.md          # Funcionarios
│   ├── MODULO_TRANSFERS.md          # Transferencias
│   ├── MODULO_RETURNS.md            # Devolucoes
│   ├── MODULO_HOLIDAY_PAYMENT.md    # Pagamento de feriados
│   ├── MODULO_DELIVERY.md           # Entregas
│   ├── MODULO_RELOCATION.md         # Remanejos
│   ├── MODULO_SALES.md              # Vendas
│   ├── MODULO_ORDEM_SERVICO.md      # Ordens de servico
│   ├── MODULO_ORDEM_PAGAMENTO.md    # Ordens de pagamento
│   ├── MODULO_STORE_GOALS.md        # Metas de loja
│   └── ... (outros modulos)
│
├── analysis/                        # Analises tecnicas
│   ├── CODE_QUALITY_ANALYSIS.md     # Analise de qualidade de codigo
│   ├── ANALISE_SEGURANCA.md         # Analise de seguranca
│   └── MODERNIZATION_AND_PATTERNS.md # Guia de modernizacao
│
└── migrations/                      # Scripts SQL
    └── *.sql
```

---

## Documentacao Principal

### Para Desenvolvedores

| Documento | Descricao |
|-----------|-----------|
| [GUIA_IMPLEMENTACAO_MODULOS.md](./GUIA_IMPLEMENTACAO_MODULOS.md) | Guia passo-a-passo para criar novos modulos |
| [PADRONIZACAO.md](./PADRONIZACAO.md) | Templates e padroes de codigo |
| [DELETE_MODAL_IMPLEMENTATION_GUIDE.md](./DELETE_MODAL_IMPLEMENTATION_GUIDE.md) | Padrao para modals de exclusao |
| [CHECKLIST_MODULOS.md](./CHECKLIST_MODULOS.md) | Checklist de verificacao |

### Regras do Projeto (Obrigatorio)

Consulte os documentos em `.claude/`:
- **REGRAS_DESENVOLVIMENTO.md** - Regras completas de desenvolvimento
- **PADROES_AVANCADOS.md** - Padroes avancados (permissoes, filtros, etc.)

---

## Modulos Documentados

### Modulos Completos (Producao)

| Modulo | Status | Arquivo |
|--------|--------|---------|
| Budget (Orcamentos) | Producao | [MODULO_BUDGET.md](./modules/MODULO_BUDGET.md) |
| Checklist | Producao | [MODULO_CHECKLIST.md](./modules/MODULO_CHECKLIST.md) |
| Chat Interno | Producao | [MODULO_CHAT.md](./modules/MODULO_CHAT.md) |
| Logger (Logs) | Producao | [MODULO_LOGGER.md](./modules/MODULO_LOGGER.md) |
| Returns (Devolucoes) | Producao | [MODULO_RETURNS.md](./modules/MODULO_RETURNS.md) |
| Holiday Payment | Producao | [MODULO_HOLIDAY_PAYMENT.md](./modules/MODULO_HOLIDAY_PAYMENT.md) |

### Modulos com Analise Disponivel

| Modulo | Arquivo |
|--------|---------|
| Employees (Funcionarios) | [MODULO_EMPLOYEES.md](./modules/MODULO_EMPLOYEES.md) |
| Transfers (Transferencias) | [MODULO_TRANSFERS.md](./modules/MODULO_TRANSFERS.md) |
| Delivery (Entregas) | [MODULO_DELIVERY.md](./modules/MODULO_DELIVERY.md) |
| Relocation (Remanejos) | [MODULO_RELOCATION.md](./modules/MODULO_RELOCATION.md) |
| Sales (Vendas) | [MODULO_SALES.md](./modules/MODULO_SALES.md) |
| Store Goals (Metas) | [MODULO_STORE_GOALS.md](./modules/MODULO_STORE_GOALS.md) |
| Ordem de Servico | [MODULO_ORDEM_SERVICO.md](./modules/MODULO_ORDEM_SERVICO.md) • [Changelog](./CHANGELOG_ORDEM_SERVICO.md) |
| Ordem de Pagamento | [MODULO_ORDEM_PAGAMENTO.md](./modules/MODULO_ORDEM_PAGAMENTO.md) |
| Usuarios | [MODULO_USUARIOS.md](./modules/MODULO_USUARIOS.md) |
| Login | [MODULO_LOGIN.md](./modules/MODULO_LOGIN.md) |
| Permissoes | [MODULO_PERMISSOES.md](./modules/MODULO_PERMISSOES.md) |
| Nivel de Acesso | [MODULO_NIVEL_ACESSO.md](./modules/MODULO_NIVEL_ACESSO.md) |
| Ajuste de Estoque | [MODULO_AJUSTE_ESTOQUE.md](./modules/MODULO_AJUSTE_ESTOQUE.md) |
| Estorno | [MODULO_ESTORNO.md](./modules/MODULO_ESTORNO.md) |
| Roteirizacao | [MODULO_ROTEIRIZACAO.md](./modules/MODULO_ROTEIRIZACAO.md) |
| Movimentacao Pessoal | [MODULO_MOVIMENTACAO_PESSOAL.md](./modules/MODULO_MOVIMENTACAO_PESSOAL.md) |
| Sincronizacao Externa | [MODULO_SINCRONIZACAO_EXTERNA.md](./modules/MODULO_SINCRONIZACAO_EXTERNA.md) |
| E-commerce | [MODULO_ECOMMERCE.md](./modules/MODULO_ECOMMERCE.md) |
| Ajustes | [MODULO_AJUSTES.md](./modules/MODULO_AJUSTES.md) |

---

## Analises Tecnicas

| Documento | Descricao |
|-----------|-----------|
| [CODE_QUALITY_ANALYSIS.md](./analysis/CODE_QUALITY_ANALYSIS.md) | Analise de qualidade e metricas do codigo |
| [ANALISE_SEGURANCA.md](./analysis/ANALISE_SEGURANCA.md) | Analise de vulnerabilidades de seguranca |
| [MODERNIZATION_AND_PATTERNS.md](./analysis/MODERNIZATION_AND_PATTERNS.md) | Guia de modernizacao arquitetural |

---

## Configuracao do Ambiente

Consulte [SETUP_ENVIRONMENT.md](./SETUP_ENVIRONMENT.md) para:
- Requisitos do sistema
- Instalacao de dependencias
- Configuracao do banco de dados
- Configuracao do servidor web

---

## Convencoes

### Nomenclatura de Arquivos

- **Controllers:** `PascalCase.php` (ex: `HolidayPayment.php`)
- **Models:** `Adms` + tipo + nome (ex: `AdmsListHolidayPayments.php`)
- **Views:** `camelCase` diretorio, `loadNome.php` arquivo
- **JavaScript:** `kebab-case.js` (ex: `holiday-payment.js`)
- **Documentacao:** `MODULO_NOME.md` para modulos

### Estrutura de Documentacao de Modulo

Todo arquivo `MODULO_*.md` deve conter:
1. Visao Geral e Status
2. Arquitetura e Estrutura de Arquivos
3. Funcionalidades
4. Banco de Dados
5. Seguranca e Permissoes
6. Troubleshooting
7. Melhorias Futuras (se aplicavel)

---

## Changelog e Atualizacoes Recentes

### 2026-02-07 - v2.2.0

**Refatoracao: AbstractConfigController**
- Criado `AbstractConfigController` como classe base para modulos de configuracao
- 13 modulos migrados: Cor, Bandeira, Situacao, Cfop, TipoPagamento, TipoPg, SituacaoPg, Rota, SituacaoTransf, SituacaoTroca, SituacaoUser, SituacaoDelivery, ResponsavelAuditoria
- Eliminados 50 models legados e reduzidas ~819 linhas de codigo duplicado
- Documentado em [PADRONIZACAO.md](./PADRONIZACAO.md) secao 20

**Refatoracao: ReversalReason (MotivoEstorno)**
- Migrado para padrao moderno AJAX/modal
- Implementados NotificationService, LoggerService, async/await JavaScript

**Seguranca**
- Correcoes CSRF: binding de token a sessao, excecoes hardcoded removidas
- Filtragem LGPD em logs (CPF, CNPJ, dados bancarios)
- Prevencao de IP spoofing no LoggerService

### 2026-01-05 - v2.1.0

**Modulo: Ordem de Servico**
- Adicionada nova secao "Nota de Transferencia e ZZnet" no modal de visualizacao
- Incluidos 4 novos campos: num_nota_transf, data_emissao_nota_transf, order_service_zznet, date_order_service_zznet
- Funcao de impressao atualizada para incluir todos os campos de auditoria
- Consulte [CHANGELOG_ORDEM_SERVICO.md](./CHANGELOG_ORDEM_SERVICO.md) para detalhes completos

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Versao:** 2.2.0
**Ultima Atualizacao:** 07/02/2026
