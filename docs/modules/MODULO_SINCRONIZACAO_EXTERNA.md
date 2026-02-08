# Análise e Plano de Implementação: Módulo de Sincronização Externa

**Autor:** Gemini
**Data:** 18 de dezembro de 2025
**Versão:** 1.0

## 1. Objetivo

Criar um módulo robusto e automatizado para sincronizar dados de um banco de dados Postgres externo com o sistema Mercury. A sincronização inicial abrangerá informações de clientes, produtos, estoque e movimentações diárias, garantindo a integridade e a consistência dos dados na aplicação local.

## 2. Visão Geral da Arquitetura

O processo de sincronização será orquestrado por um Controller específico, acionado por scripts de linha de comando (cron jobs). Este controller utilizará a classe de conexão `AdmsConnCigam` para buscar dados das views no banco externo. 

A sincronização principal será executada de forma **incremental**, buscando apenas registros alterados desde a última execução bem-sucedida. O controle de estado (último timestamp processado) será gerenciado pela tabela `sync_control_log`.

Para garantir a consistência e tratar registros excluídos na origem (que não são capturados no modo incremental), um **processo de reconciliação completa** será executado periodicamente (ex: semanalmente), comparando os dados locais com os da origem e desativando registros órfãos.

Um Service (`SyncService`) será responsável por toda a lógica de negócio: tratar, transformar e persistir os dados nas tabelas locais, utilizando Models específicos. Todo o processo deve gerar logs detalhados para monitoramento e depuração.

## 3. Pré-requisitos

- **Conexão com Banco de Dados Externo:** A classe `AdmsConnCigam` já está implementada e funcional, permitindo a conexão com o banco de dados Postgres de origem.

## 4. Fontes de Dados (Views Externas)

As seguintes views no banco de dados Postgres externo serão utilizadas como fonte de dados:

- `msl_fmovimentodiario_`: Contém os registros de movimentações diárias.
- `msl_dprodutos_`: Contém o cadastro e os detalhes dos produtos.
- `msl_dcliente_`: Contém o cadastro e os detalhes dos clientes.
- `msl_festoqueatual_`: Contém a posição atual do estoque dos produtos.

## 5. Checklist de Implementação

A implementação será dividida em fases para garantir uma abordagem estruturada e testável.

### Fase 1: Estrutura e Banco de Dados Local

- [ ] **Criar Estrutura de Diretórios:**
    - Criar a estrutura de pastas para o novo módulo. Sugestão:
        - `app/adms/Controllers/Sync/`
        - `app/adms/Models/Sync/`
        - `app/adms/Services/Sync/`

- [ ] **Definir Tabelas Locais:**
    - A estrutura de tabelas foi redesenhada para suportar o versionamento de dados (histórico de preços) e a modelagem correta de produtos com variações (SKUs).
    - **Lógica de Modelagem:**
        1.  **Produto Master/SKU:** A entidade de produto foi dividida em `sync_produtos_master` (para dados gerais da referência) e `sync_produtos_skus` (para cada variação de tamanho/cor com seu código de barras único).
        2.  **Histórico de Preços:** Os preços foram movidos para a tabela `sync_produtos_precos_historico`, permitindo rastrear todas as alterações de valor ao longo do tempo para cada SKU.
        3.  **Histórico de Estoque:** O estoque será armazenado em `sync_estoque_diario` como um snapshot diário ("fotografia"), conforme a regra de negócio.
    - **Tabelas:**

        ```sql
        CREATE TABLE sync_clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_cliente INT,
            digito_cliente VARCHAR(18),
            nome_completo VARCHAR(80),
            ddd_telefone VARCHAR(3),
            telefone VARCHAR(15),
            ddd_celular VARCHAR(3),
            celular VARCHAR(255),
            email VARCHAR(100),
            endereco VARCHAR(255),
            numero VARCHAR(60),
            complemento VARCHAR(60),
            bairro VARCHAR(255),
            uf VARCHAR(2),
            cidade VARCHAR(60),
            cep VARCHAR(9),
            tip_pessoa TEXT,
            data_cadastramento DATE,
            data_aniversario DATE,
            sexo TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(codigo_cliente)
        );
        ```

        ```sql
        CREATE TABLE sync_produtos_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referencia VARCHAR(25) NOT NULL UNIQUE,
            descricao VARCHAR(150),
            colecao VARCHAR(60),
            linha VARCHAR(50),
            marca VARCHAR(50),
            fornecedor INT,
            datacadastro DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ```

        ```sql
        CREATE TABLE sync_produtos_skus (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_master_id INT,
            codbarra VARCHAR(25) NOT NULL UNIQUE,
            refauxiliar VARCHAR(20),
            tamanho VARCHAR(8),
            cor VARCHAR(50),
            artigo VARCHAR(50),
            complartigo VARCHAR(70),
            material VARCHAR(100),
            dataatulizado DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (produto_master_id) REFERENCES sync_produtos_master(id)
        );
        ```
        
        ```sql
        CREATE TABLE sync_produtos_precos_historico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku_codbarra VARCHAR(25) NOT NULL,
            preco_venda DECIMAL(10, 2),
            preco_custo DECIMAL(10, 2),
            data_inicio_validade DATETIME NOT NULL,
            data_fim_validade DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(sku_codbarra, data_fim_validade)
        );
        ```

        ```sql
        CREATE TABLE sync_estoque_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku_codbarra VARCHAR(25) NOT NULL,
            loja VARCHAR(4) NOT NULL,
            saldo DECIMAL(10, 3),
            data_snapshot DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (sku_codbarra, loja, data_snapshot)
        );
        ```

        ```sql
        CREATE TABLE sync_movimento_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE,
            cod_lojas VARCHAR(4),
            id_controle INT,
            id_cliente INT,
            cpf VARCHAR(18),
            nf INT,
            controle INT,
            cod_consultora INT,
            cpf_consultora VARCHAR(11),
            reftam VARCHAR(25),
            cod_barras VARCHAR(20),
            venda DECIMAL(10, 2),
            custo DECIMAL(10, 2),
            valor_realizado DECIMAL(10, 2),
            desconto DECIMAL(10, 2),
            qtde DECIMAL(10, 3),
            ent_sai VARCHAR(1),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(id_controle),
            INDEX(cod_barras)
        );
        ```

        ```sql
        CREATE TABLE sync_control_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_target VARCHAR(50) NOT NULL UNIQUE,
            last_sync_timestamp DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL,
            started_at DATETIME,
            finished_at DATETIME,
            records_processed INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ```

- [ ] **Criar Migrations:**
    - Desenvolver os scripts de *migration* para as tabelas locais definidas acima, garantindo a rastreabilidade e a facilidade de deploy.

### Fase 2: Desenvolvimento do Backend

- [ ] **Criar o Service de Sincronização (`SyncService.php`):**
    - Centralizará a lógica de negócio.
    - **Método `runIncrementalSync(string $target)`:**
        1. Consulta `sync_control_log` para obter o `last_sync_timestamp` para o `$target`.
        2. Busca dados da view externa com `WHERE data_modificacao > :timestamp`.
        3. Chama métodos privados para transformar e persistir os dados.
        4. Em caso de sucesso, atualiza o `last_sync_timestamp` em `sync_control_log` com a data mais recente dos registros processados.
    - **Método `runFullReconciliation(string $target)`:**
        1. Busca a lista completa de IDs de negócio (ex: `codbarra`) da origem.
        2. Busca a lista completa de IDs de negócio locais.
        3. Compara as duas listas, identifica os registros órfãos (existem localmente, mas não na origem) e os marca como inativos no banco de dados local.
    - **Métodos privados de transformação e persistência.**

- [ ] **Criar os Models Locais (`SyncProdutosMaster.php`, `SyncProdutosSkus.php`, etc.):**
    - Criar os Models que representarão as tabelas locais e serão utilizados pelo `SyncService`.

- [ ] **Criar o Controller de Sincronização (`SyncController.php`):**
    - Orquestrará o processo, recebendo o tipo de sincronização a ser executada (incremental ou reconciliação) e chamando os métodos apropriados no `SyncService`.
    - Implementará o tratamento de exceções e o registro de logs.

### Fase 3: Automação e Monitoramento

- [ ] **Criar Scripts de Cron:**
    - **Script de Sincronização Incremental (`sync_external_data_cron.php`):** Script principal, a ser executado com alta frequência (ex: a cada 15 minutos). Será responsável por invocar o `SyncController` para executar a lógica de busca incremental para os diferentes alvos (produtos, movimentos, etc).
    - **Script de Reconciliação Completa (`reconcile_deleted_data_cron.php`):** Script secundário, a ser executado com baixa frequência (ex: uma vez por semana, de madrugada). Invocará o `SyncController` para executar a lógica de reconciliação completa, garantindo a remoção de dados órfãos.

- [ ] **Implementar Logging:**
    - Integrar um mecanismo de log no `SyncController` e `SyncService`.
    - **Logs essenciais:**
        - Início e fim de cada ciclo (incremental e reconciliação).
        - Quantidade de registros buscados, inseridos, atualizados e desativados.
        - Erros críticos que interromperam o processo.
