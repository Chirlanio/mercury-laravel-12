# Manual do Usuário - Lista da Vez
## Guia para Lojas e Gerentes de Loja

**Sistema:** Mercury - Grupo Meia Sola
**Módulo:** Lista da Vez - Atendimento
**Perfis:** Gerente de Loja (Nível 5), Nível 18, Vendedoras (Níveis 19 e 20)
**Versão:** 2.0
**Data:** Março de 2026

---

## Sumário

1. [O que é a Lista da Vez?](#1-o-que-é-a-lista-da-vez)
2. [Acesso ao Módulo](#2-acesso-ao-módulo)
3. [Conhecendo a Tela Principal](#3-conhecendo-a-tela-principal)
4. [Indicadores da Loja](#4-indicadores-da-loja)
5. [Painel: Disponíveis](#5-painel-disponíveis)
6. [Painel: Fila de Espera](#6-painel-fila-de-espera)
7. [Painel: Em Atendimento](#7-painel-em-atendimento)
8. [Painel: Em Pausa](#8-painel-em-pausa)
9. [Como Adicionar uma Consultora à Fila](#9-como-adicionar-uma-consultora-à-fila)
10. [Como Iniciar um Atendimento](#10-como-iniciar-um-atendimento)
11. [Como Finalizar um Atendimento](#11-como-finalizar-um-atendimento)
12. [Como Registrar uma Pausa](#12-como-registrar-uma-pausa)
13. [Como Finalizar uma Pausa](#13-como-finalizar-uma-pausa)
14. [Como Remover uma Consultora da Fila](#14-como-remover-uma-consultora-da-fila)
15. [Histórico do Dia](#15-histórico-do-dia)
16. [Estatísticas da Loja](#16-estatísticas-da-loja)
17. [Modo Tela Cheia (Tablet)](#17-modo-tela-cheia-tablet)
18. [Sessão do Dia](#18-sessão-do-dia)
19. [Diferenças entre Perfis](#19-diferenças-entre-perfis)
20. [Dúvidas Frequentes](#20-dúvidas-frequentes)

---

## 1. O que é a Lista da Vez?

A **Lista da Vez** é a ferramenta do Mercury que organiza a fila de atendimento das consultoras na loja. Com ela, você pode:

- Saber **quem é a próxima** consultora a atender
- Acompanhar **quanto tempo** cada atendimento está durando
- Registrar **pausas** para intervalo e almoço sem perder o lugar na fila
- Ver os **resultados** dos atendimentos do dia
- Consultar a **produtividade** de cada consultora

A fila funciona de forma justa: quem entra primeiro, atende primeiro. Ao finalizar um atendimento, a consultora pode voltar ao final da fila automaticamente.

---

## 2. Acesso ao Módulo

1. Entre no sistema Mercury com seu usuário e senha
2. No menu lateral, clique em **Lista da Vez**
3. A tela mostrará automaticamente os dados da **sua loja**

> Você verá apenas as consultoras da sua loja. Não é possível visualizar ou gerenciar outras lojas.

---

## 3. Conhecendo a Tela Principal

A tela é organizada assim:

```
+--------------------------------------------------+
|  Lista da Vez - Atendimento    [Atualizar] [Tela] |
+--------------------------------------------------+
|  Loja: NOME DA SUA LOJA                          |
+--------------------------------------------------+
|  [Total: 8]  [Na Fila: 3]  [Atendendo: 2]       |
|  [Em Pausa: 1]  [Hoje: 12]                       |
+--------------------------------------------------+
|                                                    |
|  FILA DE ESPERA (50%)   |  EM ATENDIMENTO (50%)   |
|  #1 Maria - 5 min       |  Ana - 00:12:34         |
|  #2 Julia - 3 min       |  Paula - 00:05:21       |
|  #3 Carla - 1 min       |                         |
|                                                    |
+--------------------------------------------------+
|  EM PAUSA (100%)                                   |
|  Lucia - Almoço - 00:32:15                        |
+--------------------------------------------------+
|  DISPONÍVEIS (100%)                                |
|  [Fernanda] [Beatriz] [Renata] [Sandra]           |
+--------------------------------------------------+
|  > Histórico de Atendimentos Hoje                 |
|  > Estatísticas Detalhadas                        |
+--------------------------------------------------+
```

---

## 4. Indicadores da Loja

No topo da tela, 5 cards mostram os números da sua loja neste momento:

| Card | O que mostra |
|------|-------------|
| **Total** | Quantas consultoras estão cadastradas na sua loja |
| **Na Fila** | Quantas estão aguardando na fila de espera |
| **Atendendo** | Quantas estão atendendo clientes agora |
| **Em Pausa** | Quantas estão em intervalo ou almoço |
| **Hoje** | Quantos atendimentos já foram realizados no dia |

Esses números atualizam sozinhos a cada 30 segundos.

---

## 5. Painel: Disponíveis

**Onde fica:** Na parte inferior da tela (largura total)
**Cor:** Azul
**O que mostra:** Consultoras que **não estão** na fila, nem atendendo, nem em pausa

Cada consultora aparece com:
- Foto ou iniciais coloridas
- Nome completo
- Cargo
- Botão **seta para a direita** → para colocá-la na fila

---

## 6. Painel: Fila de Espera

**Onde fica:** Lado esquerdo (metade da tela)
**Cor:** Amarelo
**O que mostra:** Consultoras aguardando para atender, em ordem

Cada consultora aparece com:
- Número da posição (#1, #2, #3...)
- Foto ou iniciais
- Nome
- Tempo que está esperando (ex: "5 min")
- Botões de ação

### Botões disponíveis na fila

| Botão | Visual | O que faz |
|-------|--------|-----------|
| **Play** (verde) | Triângulo verde | Inicia o atendimento dessa consultora |
| **Café** (azul claro) | Xícara | Coloca em pausa tipo Intervalo |
| **Talheres** (amarelo) | Garfo e faca | Coloca em pausa tipo Almoço |
| **X** (cinza) | Letra X | Remove da fila |

> **Nota:** Dependendo do seu perfil, alguns botões podem não aparecer. Veja a seção [Diferenças entre Perfis](#19-diferenças-entre-perfis).

---

## 7. Painel: Em Atendimento

**Onde fica:** Lado direito (metade da tela)
**Cor:** Verde
**O que mostra:** Consultoras que estão atendendo clientes neste momento

Cada consultora aparece com:
- Foto ou iniciais
- Nome
- Cargo
- **Cronômetro** mostrando o tempo do atendimento (ex: 00:12:34)
- Botão **check verde** para finalizar

A borda do card **pulsa** em verde, indicando que o atendimento está em andamento.

---

## 8. Painel: Em Pausa

**Onde fica:** Entre a fila/atendimento e os disponíveis (largura total)
**Cor:** Azul claro (ciano)
**Visibilidade:** Só aparece quando há alguém em pausa

Cada consultora aparece com:
- Foto ou iniciais
- Nome
- Tipo de pausa: **Intervalo** (ícone café) ou **Almoço** (ícone talheres)
- Cronômetro da pausa
- Botão verde para voltar à fila

### Alerta de tempo excedido

Se a consultora ultrapassar o tempo limite da pausa:

| Tipo | Limite |
|------|--------|
| Intervalo | 15 minutos |
| Almoço | 60 minutos |

O cronômetro ficará **vermelho** e aparecerá um **triângulo de alerta** ao lado, para que o gerente tome as providências necessárias.

---

## 9. Como Adicionar uma Consultora à Fila

**Passo a passo:**

1. Olhe o painel **Disponíveis** (azul, parte inferior)
2. Encontre a consultora desejada
3. Clique no botão com a **seta para a direita** (amarelo)
4. Pronto! A consultora aparecerá no final da **Fila de Espera** com o número da posição

A consultora recebe automaticamente a última posição. Por exemplo, se já há 3 na fila, ela entra como #4.

---

## 10. Como Iniciar um Atendimento

**Passo a passo:**

1. No painel **Fila de Espera** (amarelo), localize a consultora
2. Clique no botão **Play** (triângulo verde)
3. A consultora será movida para o painel **Em Atendimento** (verde)
4. O cronômetro começa a contar automaticamente
5. As posições das demais consultoras na fila são recalculadas

> **Dica:** Normalmente, inicia-se o atendimento da primeira consultora da fila (#1), mas é possível iniciar de qualquer posição se necessário.

---

## 11. Como Finalizar um Atendimento

**Passo a passo:**

1. No painel **Em Atendimento** (verde), encontre a consultora
2. Clique no botão **check** (verde)
3. Uma janela (modal) será aberta com as seguintes informações:

### Preenchendo o modal de finalização

**a) Resultado do Atendimento (obrigatório)**

Selecione o que aconteceu no atendimento clicando em um dos botões:
- Ex: "Venda Realizada", "Apenas Consulta", "Troca/Devolução", etc.

Os botões mudam de cor quando selecionados. É obrigatório escolher um antes de finalizar.

**b) Retornar ao final da fila**

- **Ligado** (padrão): a consultora volta automaticamente para a fila de espera
- **Desligado**: a consultora volta para o painel "Disponíveis"

**c) Observações (opcional)**

Campo de texto livre para anotar algo sobre o atendimento. Não é obrigatório.

4. Clique em **"Finalizar Atendimento"**
5. O atendimento é encerrado, o tempo total é registrado e a consultora é movida de acordo com a opção escolhida

---

## 12. Como Registrar uma Pausa

**Passo a passo:**

1. A consultora deve estar **na fila** de espera
2. No painel **Fila de Espera**, localize a consultora
3. Escolha o tipo de pausa:
   - Clique no **ícone de café** (azul claro) → **Intervalo** (15 min)
   - Clique no **ícone de talheres** (amarelo) → **Almoço** (60 min)
4. A consultora será movida para o painel **Em Pausa**
5. O cronômetro da pausa começa a contar
6. A posição original na fila é salva para quando ela voltar

> **Importante:** Só é possível colocar em pausa quem está **na fila**. Se a consultora está no painel "Disponíveis", primeiro adicione-a à fila.

---

## 13. Como Finalizar uma Pausa

**Passo a passo:**

1. No painel **Em Pausa** (azul claro), localize a consultora
2. Clique no botão verde com **ícone de seta circular** (Voltar à Fila)
3. A consultora retorna à fila de espera
4. A posição é restaurada com base na posição original (ajustada se a fila mudou)

### Exemplo de restauração de posição

- A consultora saiu para almoço na posição #3
- Enquanto almoçava, a #1 e a #2 atenderam e saíram da fila
- A fila agora tem 2 pessoas (antes eram 5)
- Ao voltar, ela entra na posição #3 (ou na última se a fila encolheu)

---

## 14. Como Remover uma Consultora da Fila

**Passo a passo:**

1. No painel **Fila de Espera**, localize a consultora
2. Clique no botão **X** (cinza)
3. A consultora volta para o painel **Disponíveis**
4. As posições das demais são recalculadas automaticamente

> **Nota:** Gerentes de nível 18 e vendedoras de nível 20 **não possuem** o botão de remover da fila. Apenas gerentes de nível 5 e administradores podem remover.

---

## 15. Histórico do Dia

Na parte inferior da página, há uma seção recolhível chamada **"Histórico de Atendimentos Hoje"**.

**Como acessar:**

1. Clique no cabeçalho cinza **"Histórico de Atendimentos Hoje"**
2. A seção se expande mostrando todos os atendimentos finalizados no dia
3. Clique novamente para fechar

**Informações exibidas:**
- Consultora que atendeu
- Horário de início e fim
- Duração do atendimento
- Resultado (venda, consulta, etc.)

---

## 16. Estatísticas da Loja

Na parte inferior da página, há uma seção recolhível chamada **"Estatísticas Detalhadas"**.

**Como acessar:**

1. Clique no cabeçalho azul claro **"Estatísticas Detalhadas"**
2. A seção se expande

### Filtrar por período

Escolha o período desejado nos botões:

| Botão | Período |
|-------|---------|
| **Hoje** | Apenas hoje (padrão) |
| **Esta Semana** | Da segunda-feira até hoje |
| **Este Mês** | Do dia 1 até hoje |
| **Personalizado** | Escolha as datas "De" e "Até" |

### O que é exibido

**4 cards de resumo:**
- Total de atendimentos no período
- Tempo médio por atendimento
- Tempo total de todos os atendimentos
- Quantidade de consultoras que atenderam

**Tabela de ranking:**

Mostra as consultoras ordenadas por número de atendimentos, com:

| Coluna | Descrição |
|--------|-----------|
| # | Posição no ranking |
| Consultora | Nome |
| Atendimentos | Quantidade total |
| Tempo Total | Soma das durações (visível no computador) |
| Tempo Médio | Duração média por atendimento |

---

## 17. Modo Tela Cheia (Tablet)

O modo tela cheia é ideal para **tablets fixos no balcão** ou **monitores** na área de vendas.

### Como ativar

- **No computador:** Clique no botão **"Tela Cheia"** no canto superior direito
- **No celular:** Abra o menu **"Ações"** e toque em **"Tela Cheia"**

### O que muda

- O menu lateral desaparece
- O cabeçalho do sistema desaparece
- Os indicadores e filtros desaparecem
- Ficam visíveis **apenas os painéis** (Fila, Atendimento, Pausa e Disponíveis)

### Como sair

- Pressione a tecla **Esc** no teclado
- Ou toque no botão de sair do modo tela cheia

> **Dica:** Use o modo tela cheia em um tablet próximo ao caixa para que todas as consultoras vejam a fila em tempo real.

---

## 18. Sessão do Dia

A Lista da Vez tem uma sessão com **validade de 12 horas**, indicada por um banner no topo da tela.

### O que o banner mostra

| Situação | Visual | Significado |
|----------|--------|-------------|
| Tudo normal | Badge **verde** com "Xh Ymin restantes" | Sessão ativa, pode trabalhar normalmente |
| Atenção | Badge **amarelo** com "X minutos restantes" | Menos de 1 hora de sessão |
| Expirada | Badge **vermelho** com "Sessão expirada" | A lista será resetada |

### A lista reseta todo dia?

**Sim.** Todos os dias a lista começa limpa. No primeiro acesso do dia:
- Entradas do dia anterior são removidas
- Atendimentos em andamento (esquecidos) são finalizados automaticamente
- A fila fica vazia, pronta para o novo dia

---

## 19. Diferenças entre Perfis

### O que cada perfil pode fazer

| Funcionalidade | Nível 5 (Gerente) | Nível 18 | Nível 19 (Vendedora) | Nível 20 |
|----------------|:------------------:|:--------:|:--------------------:|:--------:|
| Ver a lista da sua loja | Sim | Sim | Sim | Sim |
| Ver outras lojas | Não | Não | Não | Não |
| Adicionar à fila | Sim | Sim | Sim | Sim |
| Remover da fila | Sim | Não | Sim | Não |
| Reordenar a fila | Limitado | Limitado | Não | Não |
| Iniciar atendimento | Sim | Sim | Sim | Sim |
| Finalizar atendimento | Sim | Sim | Sim | Sim |
| Iniciar pausa | Sim | Sim | Não | Não |
| Finalizar pausa | Sim | Sim | Não | Não |
| Ver histórico | Sim | Sim | Sim | Sim |
| Ver estatísticas | Sim | Sim | Sim | Sim |
| Modo tela cheia | Sim | Sim | Sim | Sim |

### Resumo por perfil

**Gerente de Loja (Nível 5):**
- Acesso completo às operações da sua loja
- Pode gerenciar a fila, atendimentos e pausas
- Pode remover consultoras da fila
- Vê estatísticas e histórico completos

**Nível 18:**
- Pode gerenciar a fila e atendimentos
- Pode iniciar e finalizar pausas
- **Não pode** remover consultoras da fila

**Vendedora (Nível 19):**
- Pode entrar na fila e iniciar/finalizar atendimentos
- Pode remover da fila
- **Não pode** reordenar a fila
- **Não pode** gerenciar pausas

**Nível 20:**
- Pode entrar na fila e iniciar/finalizar atendimentos
- **Não pode** remover da fila
- **Não pode** reordenar a fila
- **Não pode** gerenciar pausas

---

## 20. Dúvidas Frequentes

### Uma consultora não aparece na lista. O que fazer?

Verifique se ela:
- Está cadastrada como **Consultora** no sistema de funcionários
- Está com status **Ativo**
- Está vinculada à **sua loja**

Se todos os critérios estiverem corretos e ela ainda não aparecer, contate o suporte.

### A fila sumiu / está vazia. É normal?

**Se é o início do dia:** Sim, a fila começa vazia todos os dias. As consultoras precisam ser adicionadas novamente.

**Se é meio do expediente:** Clique em "Atualizar" para forçar uma recarga. Se o problema persistir, pode ser que a sessão de 12 horas tenha expirado.

### O cronômetro parou de contar. O que houve?

O cronômetro é atualizado pelo navegador (JavaScript). Se ele parecer parado:
1. Clique em **"Atualizar"** para recarregar
2. Verifique se a aba do navegador não está em segundo plano há muito tempo
3. O tempo real é calculado pelo **servidor**, então o registro estará correto mesmo se o visual travar

### Posso usar no celular?

**Sim.** A tela é responsiva e se adapta a celulares. No celular:
- Os botões de ação ficam na parte inferior de cada card
- O menu principal fica num dropdown "Ações"
- Os painéis se empilham verticalmente

### A consultora voltou da pausa mas ficou na posição errada.

O sistema tenta restaurar a posição original, mas ajusta se a fila mudou. Se a fila encolheu bastante durante a pausa, a posição pode ser diferente da esperada. Nesse caso, peça ao gerente para reordenar manualmente (se tiver permissão de arrastar e soltar).

### A internet caiu. Perdi os dados?

**Não.** Todos os dados são salvos no servidor a cada ação. Quando a conexão voltar:
1. Recarregue a página
2. Os cronômetros serão recalculados com base no horário registrado no servidor
3. Nenhum atendimento ou posição na fila é perdido

### Posso ter mais de uma aba aberta?

Sim, mas não é recomendado. Todas as abas mostram os mesmos dados (atualizados a cada 30 segundos), mas ações simultâneas em abas diferentes podem causar comportamento inesperado. Use apenas **uma aba** por dispositivo.

### O que é o "Resultado do Atendimento" e por que preciso preencher?

É uma classificação do que aconteceu no atendimento — se houve venda, se foi apenas uma consulta, troca, etc. É **obrigatório** porque alimenta os relatórios de **conversão de vendas** da loja. Esses dados ajudam a diretoria a entender a performance de cada consultora e da loja como um todo.

### Como interpretar o ranking de estatísticas?

O ranking ordena as consultoras por **número de atendimentos** no período selecionado. Use-o para:
- Identificar quem está atendendo mais
- Verificar o tempo médio de atendimento (tempo muito alto pode indicar dificuldade; muito baixo pode indicar atendimento superficial)
- Acompanhar a evolução ao longo da semana/mês

---

**Manual elaborado para o Sistema Mercury - Grupo Meia Sola**
**Versão 2.0 - Março de 2026**
