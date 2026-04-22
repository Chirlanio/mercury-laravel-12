# 03 — Manual do Usuário Final de Orçamentos

> Audiência: gerentes responsáveis por área/loja, líderes que precisam
> acompanhar consumo do orçamento.
> Pré-requisito: ter `budgets.view` e `budgets.view_consumption`.
> Para configurar/criar orçamento, veja [02 — Manual do administrador](02-administrador.md).
> Para conceitos, veja [04 — Glossário](04-glossario.md).

---

## Sumário

1. [O que você consegue fazer aqui](#1-o-que-você-consegue-fazer-aqui)
2. [Como acessar](#2-como-acessar)
3. [A tela inicial](#3-a-tela-inicial)
4. [Detalhes de uma versão](#4-detalhes-de-uma-versão)
5. [Dashboard de consumo](#5-dashboard-de-consumo)
6. [Comparar duas versões](#6-comparar-duas-versões)
7. [Alertas que você vai receber](#7-alertas-que-você-vai-receber)
8. [Perguntas frequentes](#8-perguntas-frequentes)

---

## 1. O que você consegue fazer aqui

O módulo **Orçamentos** mostra **quanto sua área/loja tem disponível para
gastar no ano** e **quanto já foi consumido**. Você pode:

- ✅ Ver a versão **ativa** do orçamento (a que está valendo agora)
- ✅ Acompanhar **consumo previsto vs real** mês a mês
- ✅ Receber alertas quando uma categoria **está chegando no limite**
- ✅ Comparar versões diferentes (ex: o que mudou de `1.0` para `1.05`)
- ❌ **Não pode editar** valores se não for administrador
- ❌ **Não pode criar** versão nova se não for administrador

Para edições, fale com o controller financeiro.

> [SCREENSHOT: tela /budgets visão de gerente, com 1 versão ativa]

---

## 2. Como acessar

1. Faça login no Mercury
2. Menu lateral → **Financeiro → Orçamentos**
3. Ou direto: `/budgets`

---

## 3. A tela inicial

Você verá:

### Cards no topo

- **Total de versões ativas** — quantos orçamentos estão valendo
- **Total orçado no ano** — soma de todas as versões ativas
- **% utilização média** — quanto já foi gasto vs. orçado

### Lista de versões

Tabela com:

| Coluna | O que mostra |
|---|---|
| **Ano** | Ano do orçamento |
| **Escopo** | "Administrativo", "TI", "Geral", "Z421"… |
| **Versão** | `1.0`, `1.01`, `2.0`… |
| **Tipo** | NOVO ou AJUSTE |
| **Total ano** | Soma dos 12 meses |
| **Status** | Badge verde "ATIVO" ou cinza "INATIVO" |
| **Notas** | Observações deixadas pelo controller |

### Filtros

- **Ano** — limitar a um ano
- **Escopo** — filtrar por área/loja
- **Tipo** — só NOVO ou só AJUSTE
- **Mostrar inativos** — incluir versões substituídas

> Por padrão, lista mostra **só versões ativas**. Marque "Mostrar inativos"
> para ver histórico.

---

## 4. Detalhes de uma versão

Clique no ícone de "olho" na linha da versão. Modal mostra:

### Cabeçalho
- Ano, escopo, versão, tipo, criado por, em quando, total ano, items count

### Aba "Itens"
Lista de cada linha do orçamento:
- Conta contábil + nome
- Centro de custo + nome
- Loja (se específico)
- Fornecedor previsto
- Justificativa
- 12 valores mensais
- Total ano

### Aba "Histórico"
Eventos da versão:
- Quando foi criada (e quem subiu)
- Quando foi ativada/desativada
- Editções inline (quem editou que célula)
- Se foi para lixeira / restaurada

> [SCREENSHOT: modal de detalhes com aba Itens aberta]

### Ações disponíveis

| Ação | Quem pode |
|---|---|
| **Baixar XLSX original** | Quem tem `budgets.download` |
| **Exportar consolidado** (XLSX 6 sheets) | Quem tem `budgets.export` |
| **Ver dashboard** | Quem tem `budgets.view_consumption` |
| **Editar célula** | Quem tem `budgets.upload` (em geral admin) |
| **Excluir** | Quem tem `budgets.delete` (admin) |

---

## 5. Dashboard de consumo

A ferramenta mais útil para você. Acesse via botão **"Dashboard"** em uma
versão.

### O que mostra

Três métricas em destaque:

| Métrica | Significado |
|---|---|
| **Forecast** (orçado) | O que foi planejado para o ano |
| **Committed** (comprometido) | O que já tem **OP em pipeline** (lançada, mesmo que ainda não paga) |
| **Realized** (realizado) | OPs com status **"done"** — já efetivamente pagas |

### Como interpretar

```
Forecast:   R$ 240.000
Committed:  R$ 180.000  (75% do orçado)
Realized:   R$ 165.000  (68% do orçado)
```

Tradução: dos R$ 240k planejados, **75% já estão "comprometidos"** (OPs
lançadas que serão pagas) e **68% já saíram** efetivamente do caixa.

> A diferença entre Committed e Realized é o **saldo a pagar** das OPs já
> aprovadas.

### Visões disponíveis

Tabs no dashboard:

- **Por item**: cada linha do orçamento, com forecast vs committed vs
  realized
- **Por centro de custo**: agregado por CC
- **Por categoria contábil**: agregado por conta
- **Por mês**: matriz 12 meses × 3 métricas (gráfico de barras)

> [SCREENSHOT: dashboard com tab "Por mês" e gráfico de barras de evolução]

### Cores

- **Verde** — ≤ 60% utilização (saudável)
- **Amarelo** — 60-90% (atenção)
- **Vermelho** — > 90% (próximo ou excedido)

---

## 6. Comparar duas versões

Útil para entender **o que mudou** entre revisões.

### Como

1. Em `/budgets`, marque o checkbox de **2 versões** (vai aparecer um botão
   "Comparar" no topo)
2. Clique em **"Comparar"**

### O que vai ver

- **Adicionados** (em verde): linhas que existem na v2 e não existiam na v1
- **Removidos** (em vermelho): existiam na v1, sumiram na v2
- **Alterados** (em amarelo): mesma linha, valores diferentes — com delta
  R$ e %
- **Inalterados**: omitidos por padrão (clique para expandir, se quiser)

### Quando usar

- Auditar uma revisão de orçamento ("o controller subiu uma versão nova,
  o que mudou?")
- Justificar variações ao conselho ("entre v1.0 e v1.05, marketing
  cresceu R$ 8k — eis o porquê")
- Histórico anual ("v1.0 de 2026 vs v1.0 de 2025 — quanto cresceu cada
  área?")

> [SCREENSHOT: tela compare com 12 itens alterados, destaque em "Marketing"]

---

## 7. Alertas que você vai receber

Se você tem `budgets.view_consumption`, vai receber notificações
**diariamente às 09:00** (se o sistema estiver configurado) quando algum
CC sob seu radar atingir limites:

| Tipo | Condição | Como aparece |
|---|---|---|
| **Warning** | Utilização ≥ 70% | Email + notificação no sino do sistema. Tom: "Atenção" |
| **Exceeded** | Utilização ≥ 100% | Email + notificação. Tom: "Crítico — orçamento estourado" |

### Conteúdo do alerta

- Nome do CC
- Versão do orçamento
- Forecast vs Realized (com %)
- Link direto para o dashboard

### Desabilitar

Não há opção de auto-desativar. Se está recebendo alerta de CC que **não é
seu**, fale com o admin SaaS para revisar a permission `budgets.view_consumption`
ou ajustar escopo.

---

## 8. Perguntas frequentes

### "Vejo várias versões da mesma área. Qual está valendo?"

A versão com badge **"ATIVO"** (verde). Outras são histórico.

### "Sumiu uma versão que eu via antes"

Possibilidades:
- Foi para lixeira (admin excluiu) — peça ao admin para verificar `/budgets/trash`
- Você ativou filtro "só ativos" — desmarque para ver inativas

### "Total orçado mostra valor diferente do que vi semana passada"

Causas legítimas:
- Foi subida uma nova versão (a antiga ficou inativa)
- Houve edição inline em itens
- Restauração de versão antiga + reativação

Para auditar: abra a versão, aba **Histórico**.

### "Por que o Realized está abaixo do Committed?"

Diferença = OPs lançadas mas ainda não com status "done" (não pagas
ainda). Normal — pipeline de pagamento.

### "Posso ver consumo de meses futuros?"

Sim, mas é só forecast (não há realized para meses que ainda não chegaram).
Útil para comparar **distribuição mensal planejada** vs. **distribuição real
até agora**.

### "Posso ver dados de orçamentos de anos anteriores?"

Sim. Filtre **Ano = 2025** (ou anterior). Versões antigas continuam
disponíveis para consulta histórica.

### "Como exporto para apresentar ao conselho?"

Duas opções:
- **Baixar XLSX original**: o arquivo cru que o controller subiu
- **Exportar consolidado**: XLSX com 6 sheets (resumo, por CC, por categoria,
  por mês, dashboard, comparações) — pronto para apresentação

### "O orçado não bate com a DRE"

Possíveis causas:
- Há contas no orçamento sem mapping DRE (vão para "Não Classificado" na
  matriz)
- A matriz está em outro escopo (Geral × Loja)
- Cache da matriz desatualizado (aguarde 10 min ou peça `dre:warm-cache`)

Fale com o admin/contador para diagnosticar.

### "Recebo alertas demais"

Se o orçamento está sendo bem executado, você não deveria receber muito.
Se recebe constantemente, possíveis causas:
- Orçamento mal dimensionado (subestimou despesas)
- Mappings DRE pegando OPs erradas para o CC
- Você está com permission de muitos CCs (peça revisão)

---

## Atalhos úteis

| Atalho | Ação |
|---|---|
| Click no card "Forecast" do dashboard | Filtra dashboard por essa visão |
| `Esc` em qualquer modal | Fecha |
| Marcar 2 checkboxes na lista | Habilita botão "Comparar" |
| Click em uma barra do gráfico mensal | Drill no mês |

---

> **Última atualização:** 2026-04-22
