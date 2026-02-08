# DOCUMENTO DE ESPECIFICAÇÃO
## MÓDULO LISTA DA VEZ
### Sistema de Gestão de Fila de Atendimento

---

| Campo | Valor |
|-------|-------|
| **Versão** | 1.1 |
| **Data** | Janeiro/2026 |
| **Status** | Em Elaboração |
| **Tipo** | Módulo Integrável |

---

## 1. Introdução

### 1.1 Objetivo

Este documento especifica os requisitos funcionais e não funcionais para o desenvolvimento do Módulo Lista da Vez, um sistema de gestão de fila de atendimento que será incorporado a um sistema web existente. O módulo tem como finalidade organizar e gerenciar o rodízio de atendimento de consultoras em ambientes de vendas.

### 1.2 Escopo

O módulo Lista da Vez é responsável por controlar o fluxo de atendimento das consultoras, garantindo uma distribuição justa e organizada dos clientes. O sistema gerencia três estados principais: consultoras disponíveis, consultoras aguardando na fila e consultoras em atendimento ativo.

### 1.3 Contexto Técnico

O módulo será desenvolvido para integração com um sistema web existente que utiliza a seguinte stack tecnológica:

| Componente | Tecnologia |
|------------|------------|
| **Backend** | PHP (vanilla, sem framework) |
| **Frontend** | JavaScript (vanilla, sem framework) |
| **Estilização** | Bootstrap 4.6 |
| **Banco de Dados** | MySQL |

O desenvolvimento deve seguir os padrões e convenções já estabelecidos no sistema principal, garantindo consistência visual e técnica.

### 1.4 Definições e Abreviações

| Termo | Definição |
|-------|-----------|
| **Consultora** | Profissional responsável pelo atendimento ao cliente |
| **Fila** | Lista ordenada de consultoras aguardando para realizar atendimento |
| **Atendimento** | Período em que a consultora está atendendo um cliente |
| **Rodízio** | Sistema de alternância para distribuição equitativa de atendimentos |
| **AJAX** | Asynchronous JavaScript and XML - técnica para requisições assíncronas |
| **PDO** | PHP Data Objects - interface para acesso a banco de dados |

---

## 2. Visão Geral do Sistema

### 2.1 Descrição Geral

O módulo consiste em uma interface dividida em três painéis principais que permitem visualizar e gerenciar o status de todas as consultoras em tempo real. A tela principal apresenta:

- **Painel de Consultoras Cadastradas:** Lista completa de todas as consultoras registradas no sistema, independente do status atual.
- **Painel de Fila de Espera:** Consultoras que estão aguardando sua vez para atender, ordenadas por ordem de chegada.
- **Painel de Atendimento:** Consultoras que estão atualmente realizando atendimento a clientes.

### 2.2 Fluxo Principal

O ciclo de atendimento segue o seguinte fluxo:

1. A consultora é cadastrada no sistema e aparece no painel de consultoras cadastradas
2. A consultora entra na fila de espera quando está disponível para atender
3. Quando chega um cliente, a primeira consultora da fila inicia o atendimento
4. Ao finalizar o atendimento, a consultora pode retornar ao final da fila
5. O ciclo se repete garantindo rodízio justo entre as consultoras

### 2.3 Arquitetura do Módulo

```
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  HTML/PHP   │  │ JavaScript  │  │   Bootstrap 4.6     │  │
│  │  (Views)    │  │  (Vanilla)  │  │   (Estilização)     │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────┬───────────────────────────────────┘
                          │ AJAX/Fetch API
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                      BACKEND (PHP)                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │ Controllers │  │   Models    │  │      Helpers        │  │
│  │  (API)      │  │   (PDO)     │  │   (Validação)       │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────┬───────────────────────────────────┘
                          │ PDO/MySQLi
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                    BANCO DE DADOS                            │
│                       MySQL                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Requisitos Funcionais

### 3.1 Gestão de Consultoras

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RF001 | O sistema deve permitir cadastrar novas consultoras com nome e cargo/função | **Alta** |
| RF002 | O sistema deve permitir editar os dados de uma consultora cadastrada | Média |
| RF003 | O sistema deve permitir excluir uma consultora do sistema | Média |
| RF004 | O sistema deve exibir lista de todas as consultoras cadastradas | **Alta** |
| RF005 | O sistema deve gerar automaticamente avatar com iniciais do nome da consultora | Baixa |
| RF006 | O sistema deve exibir o status atual de cada consultora (disponível, na fila, em atendimento) | **Alta** |

### 3.2 Gestão da Fila de Espera

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RF007 | O sistema deve permitir que uma consultora entre na fila de espera | **Alta** |
| RF008 | O sistema deve permitir que uma consultora saia da fila de espera | **Alta** |
| RF009 | O sistema deve manter a ordem de chegada na fila (FIFO - First In, First Out) | **Alta** |
| RF010 | O sistema deve exibir a posição de cada consultora na fila | **Alta** |
| RF011 | O sistema deve registrar o horário de entrada na fila | Média |
| RF012 | O sistema deve exibir contador de consultoras na fila | Média |

### 3.3 Gestão de Atendimento

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RF013 | O sistema deve permitir iniciar atendimento para a primeira consultora da fila | **Alta** |
| RF014 | O sistema deve permitir finalizar um atendimento em andamento | **Alta** |
| RF015 | O sistema deve registrar o tempo de duração de cada atendimento | **Alta** |
| RF016 | O sistema deve exibir cronômetro em tempo real durante o atendimento | Média |
| RF017 | Ao finalizar atendimento, a consultora deve poder retornar automaticamente ao final da fila | **Alta** |
| RF018 | O sistema deve permitir múltiplos atendimentos simultâneos | **Alta** |
| RF019 | O sistema deve exibir contador de consultoras em atendimento | Média |

### 3.4 Interface e Visualização

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RF020 | O sistema deve exibir os três painéis lado a lado em telas grandes (grid Bootstrap) | **Alta** |
| RF021 | O sistema deve empilhar os painéis verticalmente em dispositivos móveis (responsivo Bootstrap) | **Alta** |
| RF022 | O sistema deve exibir notificações de ações realizadas (toast/alerts Bootstrap) | Média |
| RF023 | O sistema deve atualizar a interface em tempo real via AJAX sem necessidade de refresh | **Alta** |
| RF024 | O sistema deve diferenciar visualmente os três painéis utilizando classes de cores do Bootstrap | Baixa |

### 3.5 Relatórios e Histórico (Futuro)

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RF025 | O sistema deve registrar histórico de atendimentos por consultora | Baixa |
| RF026 | O sistema deve permitir visualizar estatísticas de atendimento por período | Baixa |
| RF027 | O sistema deve calcular tempo médio de atendimento por consultora | Baixa |
| RF028 | O sistema deve exportar relatórios em formato Excel/PDF | Baixa |

---

## 4. Requisitos Não Funcionais

### 4.1 Desempenho

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF001 | O sistema deve carregar a interface principal em menos de 2 segundos | **Alta** |
| RNF002 | As ações de entrada/saída da fila devem ser processadas em menos de 500ms | **Alta** |
| RNF003 | O sistema deve suportar pelo menos 50 consultoras cadastradas simultaneamente | Média |
| RNF004 | O cronômetro de atendimento deve atualizar a cada segundo sem travamentos | **Alta** |
| RNF005 | As consultas ao MySQL devem utilizar índices apropriados para otimização | **Alta** |

### 4.2 Usabilidade

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF006 | A interface deve ser intuitiva e não requerer treinamento extensivo | **Alta** |
| RNF007 | O sistema deve fornecer feedback visual para todas as ações do usuário | **Alta** |
| RNF008 | Os botões de ação devem seguir os padrões de tamanho do Bootstrap 4.6 | Média |
| RNF009 | As cores devem utilizar as classes padrão do Bootstrap para consistência | Média |

### 4.3 Compatibilidade e Portabilidade

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF010 | O sistema deve ser responsivo utilizando o grid system do Bootstrap 4.6 | **Alta** |
| RNF011 | O sistema deve ser compatível com Chrome, Firefox, Safari e Edge (últimas 2 versões) | **Alta** |
| RNF012 | O módulo deve seguir a estrutura de diretórios do sistema principal | **Alta** |
| RNF013 | O código PHP deve ser compatível com PHP 7.4 ou superior | **Alta** |
| RNF014 | O JavaScript deve ser compatível com ES6 (sem necessidade de transpilação) | **Alta** |

### 4.4 Confiabilidade

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF015 | O sistema deve persistir os dados no MySQL de forma transacional | **Alta** |
| RNF016 | O sistema deve validar todas as entradas de dados no backend (PHP) | **Alta** |
| RNF017 | O sistema deve tratar erros de forma elegante sem expor detalhes técnicos | Média |
| RNF018 | O sistema deve ter disponibilidade mínima de 99% durante horário comercial | **Alta** |

### 4.5 Segurança

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF019 | O módulo deve utilizar o sistema de autenticação/sessão do sistema principal | **Alta** |
| RNF020 | As requisições AJAX devem validar a sessão do usuário | **Alta** |
| RNF021 | Todas as queries devem utilizar prepared statements (PDO) para prevenir SQL Injection | **Alta** |
| RNF022 | Os dados de entrada devem ser sanitizados com htmlspecialchars() para prevenir XSS | **Alta** |
| RNF023 | O sistema deve implementar token CSRF nas requisições POST | **Alta** |

### 4.6 Manutenibilidade

| ID | Descrição | Prioridade |
|----|-----------|------------|
| RNF024 | O código PHP deve seguir as convenções PSR-12 | Média |
| RNF025 | O código JavaScript deve ser organizado em funções/módulos reutilizáveis | Média |
| RNF026 | O sistema deve ter documentação técnica atualizada | Média |
| RNF027 | O código deve ser versionado em repositório Git | **Alta** |

---

## 5. Plano de Implementação

### 5.1 Fases do Projeto

#### Fase 1: Preparação e Setup (1 semana)

- Análise da estrutura do sistema existente
- Definição da estrutura de diretórios do módulo
- Criação das tabelas no MySQL
- Configuração dos arquivos de conexão com banco de dados

#### Fase 2: Desenvolvimento do Backend PHP (2 semanas)

**Semana 1:**
- Criação da classe de conexão com MySQL (PDO)
- Desenvolvimento do Model de Consultoras (CRUD)
- Desenvolvimento do Model de Fila de Espera
- Desenvolvimento do Model de Atendimentos

**Semana 2:**
- Criação dos endpoints da API (arquivos PHP para requisições AJAX)
- Implementação das validações de entrada
- Implementação do controle de sessão/autenticação
- Testes das APIs via Postman/Insomnia

#### Fase 3: Desenvolvimento do Frontend (2 semanas)

**Semana 1:**
- Criação da estrutura HTML com Bootstrap 4.6
- Desenvolvimento do layout dos três painéis (grid system)
- Estilização dos cards de consultoras
- Criação dos modais (cadastro, confirmação)

**Semana 2:**
- Desenvolvimento das funções JavaScript para AJAX
- Implementação da lógica de atualização dos painéis
- Desenvolvimento do cronômetro de atendimento
- Implementação das notificações (toasts/alerts)

#### Fase 4: Integração e Testes (1 semana)

- Integração do módulo com o sistema principal
- Testes de integração end-to-end
- Testes de usabilidade com usuários
- Correções de bugs identificados
- Otimizações de performance (queries, lazy loading)

#### Fase 5: Implantação (1 semana)

- Deploy em ambiente de homologação
- Validação com usuários piloto
- Documentação de usuário
- Deploy em produção
- Treinamento da equipe

### 5.2 Cronograma Resumido

| Fase | Duração | Início | Término |
|------|---------|--------|---------|
| 1. Preparação e Setup | 1 semana | Semana 1 | Semana 1 |
| 2. Desenvolvimento Backend | 2 semanas | Semana 2 | Semana 3 |
| 3. Desenvolvimento Frontend | 2 semanas | Semana 4 | Semana 5 |
| 4. Integração e Testes | 1 semana | Semana 6 | Semana 6 |
| 5. Implantação | 1 semana | Semana 7 | Semana 7 |
| **TOTAL** | **7 semanas** | - | - |

### 5.3 Stack Tecnológica

| Camada | Tecnologia | Observações |
|--------|------------|-------------|
| **Backend** | PHP 7.4+ (vanilla) | Sem framework, seguindo padrões do sistema existente |
| **Acesso a Dados** | PDO (MySQL) | Prepared statements obrigatórios |
| **Frontend** | HTML5 + JavaScript ES6 | Vanilla JS, sem frameworks |
| **Estilização** | Bootstrap 4.6 | Utilizar componentes nativos (cards, modals, buttons, grid) |
| **Banco de Dados** | MySQL 5.7+ | Utilizar engine InnoDB para suporte a transações |
| **Comunicação** | AJAX (Fetch API ou XMLHttpRequest) | Requisições assíncronas para atualização em tempo real |

### 5.4 Estrutura de Diretórios Sugerida

```
/sistema-principal/
├── /modulos/
│   └── /lista-da-vez/
│       ├── /api/
│       │   ├── consultoras.php
│       │   ├── fila.php
│       │   └── atendimentos.php
│       ├── /classes/
│       │   ├── Database.php
│       │   ├── Consultora.php
│       │   ├── FilaEspera.php
│       │   └── Atendimento.php
│       ├── /js/
│       │   └── lista-da-vez.js
│       ├── /css/
│       │   └── lista-da-vez.css (customizações extras)
│       └── index.php (view principal)
```

### 5.5 Estrutura de Banco de Dados

Modelo de dados proposto para o módulo (MySQL):

#### Tabela: ldv_consultoras

```sql
CREATE TABLE ldv_consultoras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cargo VARCHAR(50) DEFAULT 'Consultora',
    status ENUM('disponivel', 'na_fila', 'em_atendimento') DEFAULT 'disponivel',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabela: ldv_fila_espera

```sql
CREATE TABLE ldv_fila_espera (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultora_id INT NOT NULL,
    posicao INT NOT NULL,
    entrada_fila TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultora_id) REFERENCES ldv_consultoras(id) ON DELETE CASCADE,
    UNIQUE KEY uk_consultora (consultora_id),
    INDEX idx_posicao (posicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabela: ldv_atendimentos

```sql
CREATE TABLE ldv_atendimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultora_id INT NOT NULL,
    inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fim TIMESTAMP NULL,
    duracao_segundos INT NULL,
    status ENUM('em_andamento', 'finalizado') DEFAULT 'em_andamento',
    FOREIGN KEY (consultora_id) REFERENCES ldv_consultoras(id) ON DELETE CASCADE,
    INDEX idx_consultora (consultora_id),
    INDEX idx_status (status),
    INDEX idx_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.6 Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/consultoras.php` | Lista todas as consultoras |
| POST | `/api/consultoras.php?action=create` | Cadastra nova consultora |
| POST | `/api/consultoras.php?action=update` | Atualiza dados da consultora |
| POST | `/api/consultoras.php?action=delete` | Remove consultora |
| GET | `/api/fila.php` | Lista consultoras na fila |
| POST | `/api/fila.php?action=entrar` | Consultora entra na fila |
| POST | `/api/fila.php?action=sair` | Consultora sai da fila |
| GET | `/api/atendimentos.php` | Lista atendimentos em andamento |
| POST | `/api/atendimentos.php?action=iniciar` | Inicia novo atendimento |
| POST | `/api/atendimentos.php?action=finalizar` | Finaliza atendimento |

### 5.7 Componentes Bootstrap 4.6 a Utilizar

| Componente | Uso no Módulo |
|------------|---------------|
| **Grid System** | Layout dos 3 painéis (col-lg-4, col-md-6, col-12) |
| **Cards** | Exibição de cada consultora |
| **Badges** | Indicadores de status e posição na fila |
| **Buttons** | Ações (entrar na fila, iniciar/finalizar atendimento) |
| **Modals** | Formulários de cadastro e confirmações |
| **Alerts/Toasts** | Notificações de sucesso/erro |
| **Spinners** | Loading durante requisições AJAX |
| **List Group** | Lista de consultoras nos painéis |

---

## 6. Considerações Finais

### 6.1 Premissas

- O sistema principal já possui infraestrutura de autenticação e sessões PHP
- O banco de dados MySQL está disponível e acessível
- O servidor possui PHP 7.4 ou superior instalado
- O Bootstrap 4.6 já está incluído no sistema principal
- jQuery está disponível (dependência do Bootstrap 4.6)

### 6.2 Riscos Identificados

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Incompatibilidade com estrutura do sistema existente | **Alto** | Análise técnica prévia da arquitetura atual |
| Conflitos de CSS/JS com código existente | Médio | Prefixar classes CSS e funções JS com "ldv_" |
| Resistência dos usuários à mudança | Médio | Treinamento e comunicação clara dos benefícios |
| Problemas de performance com polling AJAX | Médio | Otimizar intervalo de atualização e queries |
| Escopo adicional durante desenvolvimento | **Alto** | Gestão rigorosa de mudanças |

### 6.3 Boas Práticas a Seguir

**PHP:**
- Utilizar PDO com prepared statements para todas as queries
- Validar e sanitizar todas as entradas de dados
- Implementar tratamento de exceções (try/catch)
- Retornar respostas em formato JSON para as APIs

**JavaScript:**
- Utilizar Fetch API ou XMLHttpRequest para requisições AJAX
- Implementar tratamento de erros nas requisições
- Utilizar funções assíncronas (async/await) quando possível
- Manter código organizado em funções específicas

**MySQL:**
- Utilizar transações para operações que afetam múltiplas tabelas
- Criar índices nas colunas utilizadas em WHERE e ORDER BY
- Utilizar charset utf8mb4 para suporte completo a caracteres especiais

### 6.4 Próximos Passos

1. Validação deste documento com stakeholders
2. Aprovação formal do escopo e cronograma
3. Análise detalhada da estrutura do sistema existente
4. Elaboração do orçamento detalhado
5. Início da Fase 1 - Preparação e Setup

---

*— Fim do Documento —*
