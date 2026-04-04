# Manual do Usuário - Lista da Vez
## Guia Completo para Administração

**Sistema:** Mercury - Grupo Meia Sola
**Módulo:** Lista da Vez - Atendimento
**Perfis:** Super Admin, Admin, Suporte (Níveis 1, 2 e 3)
**Versão:** 2.0
**Data:** Março de 2026

---

## Sumário

1. [Introdução](#1-introdução)
2. [Acesso ao Módulo](#2-acesso-ao-módulo)
3. [Visão Geral da Tela](#3-visão-geral-da-tela)
4. [Cards de Estatísticas](#4-cards-de-estatísticas)
5. [Filtro por Loja](#5-filtro-por-loja)
6. [Painel: Fila de Espera](#6-painel-fila-de-espera)
7. [Painel: Em Atendimento](#7-painel-em-atendimento)
8. [Painel: Em Pausa](#8-painel-em-pausa)
9. [Painel: Disponíveis](#9-painel-disponíveis)
10. [Gerenciamento da Fila](#10-gerenciamento-da-fila)
11. [Gerenciamento de Atendimentos](#11-gerenciamento-de-atendimentos)
12. [Gerenciamento de Pausas](#12-gerenciamento-de-pausas)
13. [Histórico de Atendimentos](#13-histórico-de-atendimentos)
14. [Estatísticas Detalhadas e Ranking](#14-estatísticas-detalhadas-e-ranking)
15. [Sessão e Validade](#15-sessão-e-validade)
16. [Modo Tela Cheia](#16-modo-tela-cheia)
17. [Atualização Automática](#17-atualização-automática)
18. [Arrastar e Soltar (Drag and Drop)](#18-arrastar-e-soltar-drag-and-drop)
19. [Permissões Administrativas](#19-permissões-administrativas)
20. [Perguntas Frequentes](#20-perguntas-frequentes)

---

## 1. Introdução

A **Lista da Vez** é o módulo de gestão de fila de atendimento para consultoras (vendedoras). Ele permite controlar, em tempo real, quem está disponível, quem está na fila de espera, quem está atendendo um cliente e quem está em pausa (intervalo ou almoço).

### Para que serve?

- Organizar a ordem de atendimento de forma justa e transparente
- Acompanhar o tempo de cada atendimento em tempo real
- Gerenciar pausas (intervalos e almoços) preservando a posição na fila
- Consultar estatísticas de produtividade individual e por loja
- Manter histórico completo de atendimentos para análise gerencial

### Quem são as consultoras?

São as colaboradoras cadastradas no sistema com o cargo de **Consultora** (position_id = 1) e com status **Ativo**. Somente essas colaboradoras aparecem nos painéis da Lista da Vez.

---

## 2. Acesso ao Módulo

1. Faça login no sistema Mercury
2. No menu lateral, localize e clique em **Lista da Vez**
3. A página principal do módulo será carregada com todos os painéis

> **Nota:** Usuários administrativos (níveis 1, 2 e 3) têm acesso completo a todas as lojas e funcionalidades do módulo.

---

## 3. Visão Geral da Tela

A tela principal é dividida nas seguintes seções, de cima para baixo:

| Seção | Descrição |
|-------|-----------|
| **Cabeçalho** | Título "Lista da Vez - Atendimento" com botões de ação |
| **Banner da Sessão** | Indicador de tempo restante da sessão (validade de 12 horas) |
| **Filtro por Loja** | Dropdown para selecionar qual loja visualizar (exclusivo admin) |
| **Cards de Estatísticas** | 5 cards com contadores em tempo real |
| **Painéis do Board** | 4 painéis: Fila de Espera, Em Atendimento, Em Pausa e Disponíveis |
| **Histórico** | Seção recolhível com os atendimentos do dia |
| **Estatísticas Detalhadas** | Seção recolhível com ranking e métricas por período |

### Botões de Ação (Cabeçalho)

| Botão | Função |
|-------|--------|
| **Atualizar** (ícone de setas circulares) | Recarrega todos os painéis e estatísticas manualmente |
| **Tela Cheia** (ícone de expandir) | Ativa o modo tablet/tela cheia, ocultando menus laterais |

> No celular, os botões ficam agrupados dentro de um dropdown "Ações".

---

## 4. Cards de Estatísticas

São exibidos 5 cards na parte superior da tela com informações em tempo real:

| Card | Cor | Descrição |
|------|-----|-----------|
| **Total** | Azul (primary) | Quantidade total de consultoras cadastradas na loja |
| **Na Fila** | Amarelo (warning) | Quantas consultoras estão na fila de espera |
| **Atendendo** | Verde (success) | Quantas consultoras estão em atendimento ativo |
| **Em Pausa** | Ciano (info) | Quantas consultoras estão em intervalo ou almoço |
| **Hoje** | Ciano (info) | Total de atendimentos realizados no dia |

Os cards são atualizados automaticamente a cada 30 segundos junto com o board.

---

## 5. Filtro por Loja

**Disponível apenas para níveis 1, 2 e 3 (administradores).**

O filtro aparece como um card com fundo azul ("Filtrar por Loja") contendo um dropdown com todas as lojas cadastradas no sistema.

### Como usar

1. Clique no dropdown "Filtrar por Loja"
2. Selecione a loja desejada ou "Todas as Lojas" para visualização consolidada
3. Os painéis e estatísticas serão recarregados automaticamente com os dados da loja selecionada
4. A URL da página é atualizada com o filtro aplicado (permite compartilhar o link)

> **Dica:** Ao selecionar "Todas as Lojas", você verá consultoras de todas as unidades, com badges indicando a loja de cada uma.

---

## 6. Painel: Fila de Espera

**Cor do cabeçalho:** Amarelo (warning)
**Largura:** 50% da tela (lado esquerdo)
**Ícone:** Relógio

Este painel mostra todas as consultoras que estão aguardando para atender o próximo cliente, ordenadas por posição na fila.

### Informações exibidas por consultora

- **Badge de posição** (#1, #2, #3...) — visível em telas maiores
- **Avatar** — foto da consultora ou iniciais coloridas
- **Nome** — nome curto da consultora
- **Loja** — badge com nome da loja (quando filtro "Todas as Lojas" está ativo)
- **Tempo de espera** — quantos minutos está aguardando na fila

### Botões de ação por consultora

| Botão | Ícone | Cor | Função |
|-------|-------|-----|--------|
| **Iniciar Atendimento** | Play | Verde | Move a consultora para "Em Atendimento" |
| **Intervalo** | Xícara de café | Ciano | Inicia pausa tipo Intervalo (15 min) |
| **Almoço** | Talheres | Amarelo | Inicia pausa tipo Almoço (60 min) |
| **Sair da Fila** | X | Cinza | Remove a consultora da fila |

### Rodapé

Se o usuário tem permissão de reordenar, aparece a mensagem: *"Arraste para reordenar"*.

---

## 7. Painel: Em Atendimento

**Cor do cabeçalho:** Verde (success)
**Largura:** 50% da tela (lado direito)
**Ícone:** Headset

Este painel mostra todas as consultoras que estão atualmente atendendo um cliente.

### Informações exibidas por consultora

- **Avatar** — foto ou iniciais
- **Nome** — nome curto da consultora
- **Loja** — badge da loja
- **Cargo** — ex: "Consultora"
- **Timer** — cronômetro em tempo real no formato HH:MM:SS (atualiza a cada segundo)

### Efeito visual

Os cards de atendimento possuem uma **animação pulsante** na borda verde, indicando que o atendimento está em andamento.

### Botão de ação

| Botão | Ícone | Cor | Função |
|-------|-------|-----|--------|
| **Finalizar Atendimento** | Check | Verde | Abre modal para finalizar o atendimento |

---

## 8. Painel: Em Pausa

**Cor do cabeçalho:** Ciano (info)
**Largura:** 100% da tela
**Ícone:** Pause circle
**Visibilidade:** Só aparece quando há consultoras em pausa

Este painel mostra as consultoras que estão em intervalo ou almoço.

### Informações exibidas

- **Avatar** — iniciais coloridas
- **Nome** — nome curto
- **Loja** — badge da loja
- **Tipo de pausa** — Badge colorido: "Intervalo" (ciano, ícone café) ou "Almoço" (amarelo, ícone talheres)
- **Timer** — cronômetro em tempo real
- **Alerta de tempo excedido** — se o tempo da pausa ultrapassou o limite, o timer fica **vermelho** e aparece um ícone de **triângulo de alerta**

### Limites de pausa

| Tipo | Tempo Máximo | Cor | Ícone |
|------|-------------|-----|-------|
| **Intervalo** | 15 minutos | Ciano (info) | Xícara de café |
| **Almoço** | 60 minutos | Amarelo (warning) | Talheres |

### Botão de ação

| Botão | Ícone | Cor | Função |
|-------|-------|-----|--------|
| **Voltar à Fila** | Undo (seta circular) | Verde | Finaliza a pausa e retorna a consultora à fila |

---

## 9. Painel: Disponíveis

**Cor do cabeçalho:** Azul (primary)
**Largura:** 100% da tela
**Ícone:** Users

Este painel mostra todas as consultoras que **não estão** na fila, atendendo ou em pausa. São as consultoras que podem ser adicionadas à fila.

### Layout

Os cards são exibidos em grade responsiva:
- **Celular:** 1 card por linha
- **Tablet:** 2 cards por linha
- **Desktop grande:** 4 cards por linha

O painel tem altura máxima de 270px com barra de rolagem quando há muitas consultoras.

### Informações exibidas

- **Avatar** — foto ou iniciais (tamanho maior: 56px)
- **Nome** — nome completo
- **Cargo** — ex: "Consultora"
- **Loja** — badge da loja

### Botão de ação

| Botão | Ícone | Cor | Função |
|-------|-------|-----|--------|
| **Entrar na Fila** | Seta para direita | Amarelo (outline) | Adiciona a consultora ao final da fila |

---

## 10. Gerenciamento da Fila

### Adicionar consultora à fila

1. No painel **Disponíveis**, localize a consultora desejada
2. Clique no botão **seta para a direita** (amarelo)
3. A consultora será adicionada à **última posição** da fila
4. Os painéis e estatísticas atualizam automaticamente

### Remover consultora da fila

1. No painel **Fila de Espera**, localize a consultora
2. Clique no botão **X** (cinza)
3. A consultora voltará ao painel **Disponíveis**
4. As posições das demais são recalculadas automaticamente

### Reordenar a fila (Drag and Drop)

**Requer permissão específica.**

1. No painel **Fila de Espera**, clique e segure sobre o card da consultora
2. Arraste para a posição desejada
3. Solte o card — a nova ordem é salva automaticamente no servidor
4. Os badges de posição (#1, #2, #3...) são atualizados

> **Indicação visual durante o arraste:**
> - O card sendo arrastado fica com fundo amarelo claro
> - A posição fantasma (ghost) fica semi-transparente
> - O card arrastado ganha sombra elevada

---

## 11. Gerenciamento de Atendimentos

### Iniciar um atendimento

1. No painel **Fila de Espera**, clique no botão **Play** (verde) da consultora
2. A consultora é removida da fila e movida para o painel **Em Atendimento**
3. O cronômetro inicia automaticamente (00:00:00)
4. A posição original na fila é registrada internamente (para possível retorno)

### Finalizar um atendimento

1. No painel **Em Atendimento**, clique no botão **Check** (verde) da consultora
2. O modal **"Finalizar Atendimento"** será exibido com:

#### Campos do modal

| Campo | Obrigatório | Descrição |
|-------|-------------|-----------|
| **Avatar e Nome** | — | Identificação visual da consultora |
| **Tempo de atendimento** | — | Duração total exibida (HH:MM:SS) |
| **Resultado do Atendimento** | Sim | Botões de rádio com as opções de resultado (venda, troca, apenas consulta, etc.) |
| **Retornar ao final da fila** | — | Toggle ligado por padrão. Se desligado, a consultora volta para "Disponíveis" |
| **Observações** | Não | Campo de texto livre para anotações sobre o atendimento |

3. Selecione o **resultado do atendimento** (obrigatório)
4. Ajuste o toggle "Retornar ao final da fila" conforme necessário
5. Adicione observações se desejar
6. Clique em **"Finalizar Atendimento"**

### Resultados do atendimento (Outcomes)

Os resultados são configuráveis no banco de dados. Cada resultado possui:

- **Nome** — ex: "Venda Realizada", "Apenas Consulta", "Troca/Devolução"
- **Cor** — indicação visual (verde para conversão, amarelo para neutro, etc.)
- **Ícone** — ícone representativo
- **É conversão?** — flag para análise de taxa de conversão
- **Restaurar posição na fila?** — flag que define se a consultora volta à posição original ou ao final da fila

### Lógica de retorno à fila

Quando "Retornar ao final da fila" está ativado:

- Se o resultado tem flag **"restaurar posição"**, a consultora retorna à posição original (ajustada se a fila diminuiu durante o atendimento)
- Caso contrário, entra no **final da fila**
- Se a fila diminuiu significativamente, a posição é ajustada para a última válida

---

## 12. Gerenciamento de Pausas

### Iniciar uma pausa

1. No painel **Fila de Espera**, localize a consultora
2. Clique no botão de pausa desejado:
   - **Xícara de café** (ciano) → Intervalo (máx. 15 min)
   - **Talheres** (amarelo) → Almoço (máx. 60 min)
3. A consultora é removida da fila e movida para o painel **Em Pausa**
4. A posição original é preservada para retorno

> **Importante:** A consultora precisa estar **na fila** para iniciar uma pausa. Consultoras disponíveis ou em atendimento não podem entrar em pausa diretamente.

### Finalizar uma pausa

1. No painel **Em Pausa**, clique no botão **Voltar à Fila** (verde, ícone undo)
2. A consultora retorna à fila na posição original (ajustada se necessário)

### Tempo excedido

Quando o tempo da pausa ultrapassa o limite configurado (15 min para intervalo, 60 min para almoço):

- O timer fica **vermelho**
- Um ícone de **alerta** (triângulo com exclamação) aparece ao lado do timer
- A borda do card muda de ciano para **vermelho** (border-danger)

Isso serve como alerta visual para gestores — a pausa não é finalizada automaticamente.

---

## 13. Histórico de Atendimentos

### Acessar o histórico

1. Na parte inferior da página, clique no card cinza **"Histórico de Atendimentos Hoje"**
2. A seção se expande mostrando todos os atendimentos finalizados no dia
3. Clique novamente para recolher

### Informações exibidas

O histórico carrega via AJAX e exibe:
- Lista de atendimentos finalizados no dia
- Consultora, horário de início/fim, duração
- Resultado do atendimento

---

## 14. Estatísticas Detalhadas e Ranking

### Acessar as estatísticas

1. Na parte inferior da página, clique no card azul claro **"Estatísticas Detalhadas"**
2. A seção se expande mostrando filtros, resumo e ranking

### Filtros de período

| Botão | Período |
|-------|---------|
| **Hoje** | Apenas o dia atual (padrão) |
| **Esta Semana** | Semana corrente |
| **Este Mês** | Mês corrente |
| **Personalizado** | Campos "De" e "Até" para escolher datas |

### Cards de resumo

| Card | Descrição |
|------|-----------|
| **Total Atendimentos** | Soma de todos os atendimentos no período |
| **Tempo Médio** | Duração média dos atendimentos |
| **Tempo Total** | Soma de todas as durações |
| **Consultoras Ativas** | Quantas consultoras atenderam no período |

### Tabela de ranking

| Coluna | Descrição |
|--------|-----------|
| **#** | Posição no ranking |
| **Consultora** | Nome da consultora |
| **Atendimentos** | Total de atendimentos no período |
| **Tempo Total** | Soma de duração (visível apenas em desktop) |
| **Tempo Médio** | Duração média por atendimento |

---

## 15. Sessão e Validade

A Lista da Vez opera com uma **sessão de 12 horas** para garantir a integridade dos dados.

### Banner da sessão

No topo da página, um banner indica o tempo restante:

| Estado | Visual | Significado |
|--------|--------|-------------|
| **> 1 hora restante** | Badge verde "Xh Ymin restantes" | Sessão saudável |
| **< 1 hora restante** | Badge amarelo "X minutos restantes" + alerta amarelo | Sessão próxima de expirar |
| **Expirada** | Badge vermelho "Sessão expirada" + alerta vermelho | Dados serão limpos automaticamente |

### O que acontece quando a sessão expira?

O sistema executa limpeza automática a cada carregamento de página:

1. **Entradas da fila** com mais de 12 horas são removidas
2. **Atendimentos ativos** com mais de 12 horas são finalizados automaticamente (sem retorno à fila)
3. **Pausas ativas** com mais de 12 horas são finalizadas automaticamente
4. **Entradas do dia anterior** são removidas
5. As posições são recalculadas para eliminar lacunas

### Novo dia

A cada novo dia, a lista começa limpa. Entradas do dia anterior são removidas automaticamente no primeiro acesso.

---

## 16. Modo Tela Cheia

O modo tela cheia é ideal para uso em **tablets** fixos nas lojas ou em **monitores** dedicados.

### Ativar

- Clique no botão **"Tela Cheia"** (ícone de expandir) no cabeçalho
- Ou no celular: Ações → Tela Cheia

### O que muda

- O menu lateral e o cabeçalho do sistema são **ocultados**
- O banner de sessão e o filtro de loja são **ocultados**
- Os cards de estatísticas são **ocultados**
- Apenas os **painéis do board** ficam visíveis, ocupando toda a tela

### Sair

- Pressione a tecla **Escape** no teclado
- Ou clique no botão de sair do modo tela cheia (se disponível)

---

## 17. Atualização Automática

O board é atualizado automaticamente a cada **30 segundos** via AJAX. Isso garante que todos os usuários vejam as mesmas informações em tempo real.

### O que é atualizado

- Painéis (fila, atendimento, pausa, disponíveis)
- Cards de estatísticas
- Timers de atendimento e pausa
- Contadores nos cabeçalhos dos painéis

### Atualização manual

Clique no botão **"Atualizar"** (ícone de setas circulares) a qualquer momento para forçar uma atualização imediata.

### Timers

Os timers de atendimento e pausa são atualizados **a cada segundo** via JavaScript, independente do ciclo de 30 segundos. Eles mostram o tempo decorrido no formato HH:MM:SS com fonte monospace.

---

## 18. Arrastar e Soltar (Drag and Drop)

O módulo suporta arrastar e soltar (drag and drop) para operações rápidas.

### Reordenar a fila

- Arraste cards **dentro** do painel Fila de Espera para mudar posições
- Requer permissão de reordenação

### Indicações visuais

| Estado | Visual |
|--------|--------|
| **Card sendo arrastado** | Sombra elevada, fundo amarelo claro |
| **Posição fantasma** | Semi-transparente (40% opacidade) |
| **Card selecionado** | Fundo amarelo claro (chosen) |

---

## 19. Permissões Administrativas

### Matriz de permissões por nível

| Funcionalidade | Nível 1 (Super Admin) | Nível 2 (Admin) | Nível 3 (Suporte) |
|----------------|:-----:|:-----:|:-----:|
| Filtrar por loja | Sim | Sim | Sim |
| Ver todas as lojas | Sim | Sim | Sim |
| Adicionar à fila | Sim | Sim | Sim |
| Remover da fila | Sim | Sim | Sim |
| Reordenar fila | Sim | Sim | Sim |
| Iniciar atendimento | Sim | Sim | Sim |
| Finalizar atendimento | Sim | Sim | Sim |
| Iniciar pausa | Sim | Sim | Sim |
| Finalizar pausa | Sim | Sim | Sim |
| Ver estatísticas | Sim | Sim | Sim |
| Ver histórico | Sim | Sim | Sim |
| Modo tela cheia | Sim | Sim | Sim |

> **Nota:** Administradores possuem acesso irrestrito. A diferença principal em relação às lojas é a possibilidade de **filtrar qualquer loja** e ver dados consolidados ("Todas as Lojas").

---

## 20. Perguntas Frequentes

### Por que uma consultora não aparece nos painéis?

A consultora precisa atender a **todos** os critérios:
- Estar cadastrada na tabela de funcionários (`adms_employees`)
- Ter o cargo **Consultora** (position_id = 1)
- Estar com status **Ativo** (adms_status_employee_id = 2)
- Estar vinculada a uma loja

### A fila reseta todo dia?

**Sim.** A lista é limpa automaticamente no início de cada novo dia. A primeira pessoa a acessar o módulo no dia dispara a limpeza das entradas do dia anterior.

### O que acontece se a internet cair durante um atendimento?

O atendimento permanece ativo no servidor. Quando a conexão for restabelecida, o timer será recalculado com base no horário de início registrado no banco de dados. Nenhum dado é perdido.

### Posso ter consultoras de lojas diferentes na mesma fila?

Ao filtrar por "Todas as Lojas", é possível visualizar consultoras de todas as lojas simultaneamente. No entanto, a fila é gerenciada **por loja** — cada loja tem sua própria fila independente.

### O que significa "Resultado do Atendimento"?

São categorias pré-configuradas que classificam o desfecho do atendimento (ex: "Venda Realizada", "Apenas Consulta", "Troca"). Isso alimenta métricas de **taxa de conversão** de vendas.

### Posso alterar os tipos de pausa e seus limites?

Os tipos de pausa (Intervalo e Almoço) e seus limites (15 e 60 minutos) são configurados no banco de dados (tabela `ldv_break_types`). Alterações devem ser feitas por um administrador de banco de dados.

### O timer da pausa finaliza automaticamente quando excede o limite?

**Não.** O sistema apenas exibe um alerta visual (timer vermelho + ícone de alerta). A pausa deve ser finalizada manualmente por um gestor. Isso é intencional para permitir flexibilidade operacional.

### Como funciona a restauração de posição?

Quando uma consultora finaliza um atendimento ou pausa e retorna à fila:
1. O sistema verifica a posição original antes da saída
2. Se a fila diminuiu (consultoras saíram), a posição é ajustada para o máximo válido
3. As demais posições são reorganizadas automaticamente

### Por que o campo "Resultado" é obrigatório?

O resultado é obrigatório para alimentar as métricas de conversão de vendas. Sem essa informação, não seria possível calcular taxas de conversão nas estatísticas e relatórios.

---

**Manual elaborado para o Sistema Mercury - Grupo Meia Sola**
**Versão 2.0 - Março de 2026**
