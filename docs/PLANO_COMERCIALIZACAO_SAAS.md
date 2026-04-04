# Plano de Comercialização SaaS - Mercury

**Documento:** Plano Estratégico de Produto
**Versão:** 1.0
**Data:** 19 de Fevereiro de 2026
**Classificação:** Confidencial - Uso Interno
**Elaborado por:** Equipe de Tecnologia - Grupo Meia Sola

---

## Sumário Executivo

O Mercury é uma plataforma web de gestão empresarial desenvolvida internamente pelo Grupo Meia Sola, atualmente com **110 módulos** cobrindo processos como gestão de funcionários, vendas, estoque, transferências, solicitações de pagamento, estornos, controle de férias, escalas de trabalho, entre outros.

Este documento apresenta o plano para transformar o Mercury em um produto comercializável no modelo **SaaS (Software as a Service)** com cobrança por mensalidade, permitindo que outras empresas utilizem a plataforma para gerenciar suas operações internas.

### Oportunidade

- Mercado de gestão empresarial para PMEs em crescimento constante
- Plataforma já validada em operação real com múltiplas lojas
- Custo marginal baixo para cada novo cliente após a adaptação inicial
- Receita recorrente previsível (modelo de assinatura mensal)

---

## 1. Visão Geral do Produto Atual

### 1.1 Stack Tecnológico

| Componente | Tecnologia |
|---|---|
| Linguagem | PHP 8.0+ |
| Banco de Dados | MySQL (primário) |
| Frontend | Bootstrap 4.6.1 + JavaScript ES6+ |
| Servidor | Apache (WAMP) |
| Arquitetura | MVC próprio |

### 1.2 Números do Projeto

| Métrica | Quantidade |
|---|---|
| Módulos | 110 |
| Controllers | 574 |
| Models | 649 |
| Views | 683 |
| Arquivos JavaScript | 91 |
| Services | 25 |

### 1.3 Módulos Principais

| Categoria | Módulos |
|---|---|
| **RH / Pessoas** | Funcionários, Contratos, Escalas de Trabalho, Férias, Treinamentos |
| **Comercial** | Vendas, Metas de Loja, Indicações, Fila de Atendimento |
| **Financeiro** | Solicitações de Pagamento, Estornos, Controle de Pedidos |
| **Logística** | Transferências, Remanejos, Rotas de Entrega |
| **Operacional** | Checklists, Ordens de Serviço, Solicitações de Material |
| **Comunicação** | Chat Interno, Notificações, Helpdesk |
| **Administrativo** | Usuários, Permissões, Níveis de Acesso, Menus Dinâmicos |

---

## 2. Modelo de Negócio Proposto

### 2.1 Estrutura de Planos

| Característica | Starter | Professional | Enterprise |
|---|---|---|---|
| **Preço sugerido** | R$ X/mês | R$ Y/mês | Sob consulta |
| **Usuários** | Até 10 | Até 50 | Ilimitado |
| **Unidades/Lojas** | 1 | Até 10 | Ilimitado |
| **Módulos** | Essenciais | Todos | Todos + customizações |
| **Armazenamento** | 5 GB | 25 GB | 100 GB+ |
| **Suporte** | E-mail (48h) | Prioritário (24h) | Dedicado + SLA |
| **Relatórios** | Básicos | Avançados | Personalizados |
| **Backup** | Diário | Diário + sob demanda | Tempo real |

### 2.2 Módulos por Plano

**Plano Starter (Essenciais):**
- Cadastro de Funcionários e Contratos
- Vendas e Metas
- Controle de Usuários e Permissões
- Chat Interno
- Notificações

**Plano Professional (Completo):**
- Todos do Starter
- Transferências e Remanejos
- Solicitações de Pagamento e Estornos
- Controle de Férias
- Checklists e Ordens de Serviço
- Escalas de Trabalho
- Relatórios Avançados e Exportação

**Plano Enterprise:**
- Todos do Professional
- Integrações com ERP (customizado)
- Módulos sob demanda
- API para integrações externas

### 2.3 Projeção de Receita (Cenário Conservador)

| Período | Clientes Estimados | Receita Mensal Estimada |
|---|---|---|
| Mês 1-6 | 3 a 5 | R$ X a R$ Y |
| Mês 7-12 | 10 a 15 | R$ X a R$ Y |
| Ano 2 | 25 a 40 | R$ X a R$ Y |

*Valores a serem definidos com base no estudo de mercado e precificação.*

---

## 3. Arquitetura Multi-Tenant

### 3.1 Estratégia Escolhida: Banco de Dados Separado por Cliente

Cada cliente (tenant) terá seu próprio banco de dados MySQL, totalmente isolado dos demais.

```
                    ┌─────────────────────┐
                    │   Load Balancer /    │
                    │   Servidor Web       │
                    └──────────┬──────────┘
                               │
                    ┌──────────┴──────────┐
                    │   Aplicação Mercury  │
                    │   (Código Único)     │
                    │                      │
                    │  ┌────────────────┐  │
                    │  │ TenantResolver │  │
                    │  │ (subdomínio)   │  │
                    │  └───────┬────────┘  │
                    └──────────┼──────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
    ┌─────────┴──────┐ ┌──────┴───────┐ ┌──────┴───────┐
    │ mercury_master │ │ mercury_     │ │ mercury_     │
    │ (gestão SaaS)  │ │ cliente_a    │ │ cliente_b    │
    │                │ │              │ │              │
    │ - tenants      │ │ - usuarios   │ │ - usuarios   │
    │ - planos       │ │ - employees  │ │ - employees  │
    │ - faturas      │ │ - vendas     │ │ - vendas     │
    │ - logs globais │ │ - etc...     │ │ - etc...     │
    └────────────────┘ └──────────────┘ └──────────────┘
```

### 3.2 Justificativa da Escolha

| Critério | Banco Separado | Banco Compartilhado (tenant_id) |
|---|---|---|
| **Isolamento de dados** | Total | Lógico (risco de vazamento) |
| **Impacto no código atual** | Baixo | Altíssimo (todas as queries) |
| **Performance** | Independente por cliente | Degrada com volume |
| **Backup/Restore** | Por cliente | Complexo |
| **Exclusão de cliente** | DROP DATABASE | Complexo |
| **Conformidade LGPD** | Facilitada | Difícil |
| **Custo de infraestrutura** | Maior | Menor |
| **Escalabilidade** | Até ~200 clientes* | Milhares |

*Para escalar além de 200 clientes, pode-se distribuir em múltiplos servidores de banco.*

### 3.3 Funcionamento

1. Cliente acessa: `empresa.mercury.com.br`
2. O sistema identifica o tenant pelo subdomínio
3. Conecta ao banco de dados correspondente (`mercury_empresa`)
4. Toda a aplicação funciona normalmente com dados isolados

### 3.4 Banco Master (Gestão do SaaS)

Um banco de dados central gerencia todos os tenants:

| Tabela | Função |
|---|---|
| `tenants` | Cadastro de clientes (nome, subdomínio, plano, status) |
| `tenant_plans` | Definição dos planos e limites |
| `tenant_modules` | Módulos habilitados por tenant |
| `tenant_invoices` | Controle de faturas e pagamentos |
| `tenant_usage` | Métricas de uso (usuários, storage) |
| `saas_admins` | Administradores da plataforma SaaS |
| `saas_logs` | Logs globais de operações |

---

## 4. Adaptações Necessárias no Mercury

### 4.1 Mudanças de Alta Prioridade

#### 4.1.1 Sistema de Resolução de Tenant
Criar mecanismo para identificar o cliente pela URL e conectar ao banco correto.

**Impacto:** Alteração no `core/Config.php` e `core/ConfigController.php`

#### 4.1.2 Conexão Dinâmica com Banco de Dados
Hoje a conexão é fixa em um único banco. Precisa selecionar o banco baseado no tenant.

**Impacto:** Alteração no `AdmsConn.php` (helper de conexão)

#### 4.1.3 Generalização de Constantes
Remover referências específicas ao Grupo Meia Sola. Tornar configuráveis por tenant:
- Nome da empresa
- Logo e cores
- Configurações de e-mail (SMTP)
- Timezone
- Moeda e formato de números

**Impacto:** Alteração no `core/Config.php`, views de layout, templates de e-mail

#### 4.1.4 Isolamento de Uploads
Separar arquivos por tenant para evitar conflitos e garantir isolamento:

```
Atual:                          Proposto:
assets/imagens/employees/       storage/{tenant}/employees/
assets/imagens/helpdesk/        storage/{tenant}/helpdesk/
assets/imagens/products/        storage/{tenant}/products/
```

**Impacto:** Alteração em todos os controllers/models que manipulam arquivos

### 4.2 Mudanças de Média Prioridade

#### 4.2.1 Controle de Módulos por Plano
Sistema para habilitar/desabilitar módulos conforme o plano contratado.

**Impacto:** Middleware no `ConfigController.php` + tabela de controle

#### 4.2.2 Limites por Plano
Verificação de limites antes de operações de criação:
- Número máximo de usuários
- Número máximo de lojas/unidades
- Espaço de armazenamento

**Impacto:** Middleware + verificações nos controllers de criação

#### 4.2.3 Painel Administrativo SaaS
Interface para a equipe gerenciar:
- Criar/suspender/reativar tenants
- Visualizar métricas de uso
- Gerenciar planos e módulos
- Controle financeiro (faturas)

**Impacto:** Novo módulo completo (Controllers, Models, Views)

#### 4.2.4 Onboarding Automatizado
Script/processo para provisionar novo cliente:
1. Criar banco de dados
2. Executar migrations (criar tabelas)
3. Popular dados iniciais (níveis de acesso, páginas, menus)
4. Criar usuário administrador do cliente
5. Configurar subdomínio

**Impacto:** Scripts de automação + interface no painel SaaS

### 4.3 Mudanças de Baixa Prioridade (Pós-Lançamento)

- API REST para integrações externas
- Webhooks para notificações
- Customização visual (cores/tema) por cliente
- Marketplace de módulos adicionais
- Dashboard de métricas para o cliente

---

## 5. Estimativa de Esforço

### 5.1 Fase 1 - Fundação Multi-Tenant

| Tarefa | Complexidade | Estimativa |
|---|---|---|
| Sistema de resolução de tenant (subdomínio) | Média | 1 semana |
| Conexão dinâmica com banco (`AdmsConn`) | Média | 1 semana |
| Banco master (tabelas de gestão SaaS) | Média | 1 semana |
| Generalização de constantes e configs | Alta | 2 semanas |
| Isolamento de uploads por tenant | Alta | 2 semanas |
| Remoção de referências ao Grupo Meia Sola | Baixa | 1 semana |
| Adaptação do sistema de sessão por tenant | Média | 1 semana |
| Script de provisioning de novo tenant | Média | 1 semana |
| **Subtotal Fase 1** | | **~10 semanas** |

### 5.2 Fase 2 - Gestão e Controle

| Tarefa | Complexidade | Estimativa |
|---|---|---|
| Painel administrativo SaaS (CRUD tenants) | Alta | 3 semanas |
| Controle de módulos por plano | Média | 2 semanas |
| Sistema de limites por plano | Média | 1 semana |
| Onboarding automatizado (wizard) | Alta | 2 semanas |
| Tela de login com seleção de tenant | Baixa | 1 semana |
| Sistema de migrations versionadas | Média | 2 semanas |
| **Subtotal Fase 2** | | **~11 semanas** |

### 5.3 Fase 3 - Produção e Segurança

| Tarefa | Complexidade | Estimativa |
|---|---|---|
| Migração para servidor Linux (Nginx + PHP-FPM) | Média | 2 semanas |
| Configuração HTTPS e certificados SSL | Baixa | 3 dias |
| Sistema de backup automatizado por tenant | Média | 1 semana |
| Monitoramento e alertas | Média | 1 semana |
| Hardening de segurança para SaaS | Alta | 2 semanas |
| Audit log completo | Média | 1 semana |
| Rate limiting e proteção contra abuso | Média | 1 semana |
| **Subtotal Fase 3** | | **~9 semanas** |

### 5.4 Fase 4 - Compliance e Lançamento

| Tarefa | Complexidade | Estimativa |
|---|---|---|
| Adequação LGPD (termos, consentimento, exclusão) | Alta | 2 semanas |
| Documentação para o cliente final | Média | 2 semanas |
| Testes de carga e performance | Média | 1 semana |
| Testes de segurança (pentest básico) | Alta | 1 semana |
| Correções e ajustes finais | Variável | 2 semanas |
| **Subtotal Fase 4** | | **~8 semanas** |

### 5.5 Resumo de Esforço

| Fase | Duração Estimada | Acumulado |
|---|---|---|
| Fase 1 - Fundação Multi-Tenant | ~10 semanas | 10 semanas |
| Fase 2 - Gestão e Controle | ~11 semanas | 21 semanas |
| Fase 3 - Produção e Segurança | ~9 semanas | 30 semanas |
| Fase 4 - Compliance e Lançamento | ~8 semanas | 38 semanas |
| **Total Estimado** | | **~38 semanas (~9 meses)** |

> **Nota:** Estimativas consideram 1 desenvolvedor dedicado em tempo integral. Com 2 desenvolvedores trabalhando em paralelo (fases independentes), o prazo pode ser reduzido para aproximadamente **5 a 6 meses**.

> **Nota 2:** Estas estimativas podem variar conforme a complexidade encontrada durante a implementação e prioridades do negócio. Recomenda-se revisão ao final de cada fase.

---

## 6. Infraestrutura e Custos

### 6.1 Infraestrutura Necessária

| Componente | Especificação Inicial | Custo Estimado/mês |
|---|---|---|
| Servidor de Aplicação (VPS) | 4 vCPU, 8GB RAM, 100GB SSD | R$ 150 - R$ 300 |
| Servidor de Banco MySQL | 2 vCPU, 4GB RAM, 50GB SSD | R$ 100 - R$ 200 |
| Domínio + SSL Wildcard | *.mercury.com.br | R$ 30 - R$ 80/ano |
| Serviço de E-mail (SMTP) | SendGrid, Mailgun ou similar | R$ 0 - R$ 100 |
| Backup externo | S3 ou similar | R$ 20 - R$ 50 |
| **Total Infraestrutura** | | **R$ 300 - R$ 730/mês** |

> Custos escalam conforme número de clientes. Para 10+ clientes, considerar upgrade de infraestrutura.

### 6.2 Comparativo de Hospedagem

| Provedor | Vantagens | Faixa de Preço |
|---|---|---|
| DigitalOcean | Simples, preço previsível | US$ 24 - 48/mês |
| AWS (Lightsail/EC2) | Escalabilidade, serviços gerenciados | US$ 30 - 80/mês |
| Contabo | Custo muito baixo, bom hardware | EUR 10 - 30/mês |
| Locaweb/KingHost | Suporte em português, servidores no BR | R$ 100 - 400/mês |

---

## 7. Banco de Dados: MySQL vs PostgreSQL

### 7.1 Análise Comparativa

| Aspecto | MySQL 8.0+ | PostgreSQL 15+ |
|---|---|---|
| Adequação para SaaS | Excelente | Excelente |
| Multi-tenancy (banco separado) | Suporte completo | Suporte completo |
| Performance CRUD | Excelente | Excelente |
| Custo de hospedagem | Mais opções, geralmente menor | Competitivo |
| Compatibilidade com Mercury | **Total (já em uso)** | Requer migração |
| Esforço de migração | Zero | 3 - 5 meses adicionais |
| Curva de aprendizado da equipe | Nenhuma | Significativa |

### 7.2 Decisão

**MySQL é a escolha correta para este projeto.** Justificativas:

1. **Zero reescrita** - O Mercury já utiliza MySQL com PDO prepared statements em todas as 649 models
2. **MySQL 8.0+ é robusto** - Suporta CTEs, Window Functions, JSON, replicação nativa
3. **Isolamento por banco** - Funciona perfeitamente com a estratégia de banco separado por tenant
4. **Custo de oportunidade** - Migrar para PostgreSQL adicionaria 3 a 5 meses ao projeto sem ganho funcional
5. **Mercado** - MySQL é o banco mais utilizado em aplicações web PHP, com ampla documentação e suporte

### 7.3 Quando Reavaliar

Considerar PostgreSQL futuramente apenas se:
- Necessidade de schemas nativos por tenant (acima de 200 clientes)
- Requisitos de geolocalização avançada (PostGIS)
- Integrações que exijam tipos de dados específicos do PostgreSQL

---

## 8. Segurança e Compliance

### 8.1 Requisitos de Segurança para SaaS

| Requisito | Status Atual | Ação Necessária |
|---|---|---|
| SQL Injection Prevention | Implementado (PDO) | Manter |
| XSS Prevention | Implementado (htmlspecialchars) | Manter |
| CSRF Protection | Implementado (global) | Manter |
| HTTPS | Não implementado | Implementar (obrigatório) |
| Rate Limiting | Não implementado | Implementar |
| Isolamento de dados por tenant | Não aplicável (single-tenant) | Implementar |
| Audit Log | Parcialmente implementado | Expandir |
| Backup automatizado | Não implementado | Implementar |
| Criptografia de dados sensíveis | Parcial | Expandir |
| Política de senhas | Implementada (12+ chars) | Manter |
| Session hardening | Implementado | Manter |

### 8.2 LGPD - Lei Geral de Proteção de Dados

Para comercializar o software, é obrigatório estar em conformidade com a LGPD:

| Requisito LGPD | Implementação |
|---|---|
| Termos de Uso e Política de Privacidade | Documento jurídico + aceite no cadastro |
| Consentimento para coleta de dados | Checkbox explícito no primeiro acesso |
| Direito de acesso aos dados | Funcionalidade de exportação de dados |
| Direito de exclusão (esquecimento) | Funcionalidade de exclusão completa do tenant |
| Encarregado de dados (DPO) | Designar responsável |
| Registro de operações de tratamento | Audit log completo |
| Notificação de incidentes | Processo definido + template de comunicação |

---

## 9. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Vazamento de dados entre tenants | Baixa | Crítico | Banco separado + testes automatizados |
| Indisponibilidade do serviço | Média | Alto | Monitoramento + backup + redundância |
| Cliente não paga mensalidade | Alta | Médio | Suspensão automática + período de graça |
| Performance degrada com muitos clientes | Média | Alto | Monitoramento + escalabilidade horizontal |
| Escopo cresce durante desenvolvimento | Alta | Médio | Fases bem definidas + entregas incrementais |
| Falha de segurança explorada | Baixa | Crítico | Pentest + hardening + atualizações |
| Concorrência com ERPs estabelecidos | Alta | Médio | Foco em nicho + preço competitivo + agilidade |

---

## 10. Cronograma Visual

```
2026
Mar    Abr    Mai    Jun    Jul    Ago    Set    Out    Nov    Dez
 |      |      |      |      |      |      |      |      |      |
 |====FASE 1 - FUNDAÇÃO====|      |      |      |      |      |
 |  Multi-tenant + Configs  |      |      |      |      |      |
 |      |      |      |      |      |      |      |      |      |
        |=====FASE 2 - GESTÃO======|      |      |      |      |
        | Painel SaaS + Controles  |      |      |      |      |
        |      |      |      |      |      |      |      |      |
                      |====FASE 3 - PRODUÇÃO====|      |      |
                      | Infra + Segurança       |      |      |
                      |      |      |      |      |      |      |
                                    |===FASE 4 - LANÇAMENTO===|
                                    | LGPD + Testes + Go-live |
                                    |      |      |      |      |
                                                          |
                                                    LANÇAMENTO
                                                    (Dez/2026)
```

> Com 2 desenvolvedores, fases podem ser paralelizadas e o lançamento antecipado para **Set-Out/2026**.

---

## 11. Próximos Passos

### Decisões Necessárias da Diretoria

1. **Aprovação do investimento** - Dedicação de 1-2 desenvolvedores por 6-9 meses
2. **Definição de preços** - Valores dos planos Starter, Professional e Enterprise
3. **Nome comercial** - Manter "Mercury" ou criar marca específica para o produto SaaS
4. **Infraestrutura** - Aprovação do custo mensal de servidores (R$ 300-730/mês inicial)
5. **Jurídico** - Contratação de assessoria para Termos de Uso, Contrato e LGPD
6. **Primeiro cliente piloto** - Identificar empresa parceira para teste beta

### Ações Imediatas (Pós-Aprovação)

1. Criação de branch dedicado para desenvolvimento SaaS
2. Início da Fase 1 (resolução de tenant + conexão dinâmica)
3. Contratação de servidor de homologação
4. Início da documentação jurídica

---

## 12. Conclusão

O Mercury possui uma base sólida com 110 módulos validados em operação real. A transformação para SaaS é viável e representa uma oportunidade significativa de receita recorrente com custo marginal decrescente por cliente.

A estratégia de banco separado por tenant garante isolamento total dos dados com mínimo impacto no código existente. O MySQL atende plenamente às necessidades técnicas, eliminando a necessidade de migração de banco de dados.

O investimento estimado de **6 a 9 meses de desenvolvimento** posiciona o produto para lançamento comercial no segundo semestre de 2026, com potencial de retorno sobre o investimento a partir do primeiro ano de operação.

---

**Documento preparado para apresentação à Diretoria do Grupo Meia Sola.**
**Fevereiro de 2026.**
