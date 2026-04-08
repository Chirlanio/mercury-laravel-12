<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Mercury SaaS - Manual de Administração</title>
    <style>
        @page {
            margin: 2cm 2.5cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.6;
        }

        /* Cover page */
        .cover {
            text-align: center;
            padding-top: 180px;
            page-break-after: always;
        }
        .cover h1 {
            font-size: 28pt;
            color: #4338ca;
            margin-bottom: 8px;
        }
        .cover h2 {
            font-size: 16pt;
            color: #6366f1;
            font-weight: normal;
            margin-bottom: 60px;
        }
        .cover .meta {
            font-size: 10pt;
            color: #6b7280;
        }
        .cover .meta p {
            margin: 4px 0;
        }

        /* TOC */
        .toc {
            page-break-after: always;
        }
        .toc h2 {
            font-size: 16pt;
            color: #4338ca;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        .toc-item {
            display: block;
            padding: 6px 0;
            border-bottom: 1px dotted #d1d5db;
            text-decoration: none;
            color: #1f2937;
            font-size: 10pt;
        }
        .toc-item.level2 {
            padding-left: 20px;
            font-size: 9pt;
            color: #4b5563;
        }

        /* Headings */
        h1 {
            font-size: 18pt;
            color: #4338ca;
            border-bottom: 3px solid #4338ca;
            padding-bottom: 6px;
            margin-top: 0;
            page-break-before: always;
        }
        h1:first-of-type {
            page-break-before: avoid;
        }
        h2 {
            font-size: 13pt;
            color: #4f46e5;
            margin-top: 24px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 11pt;
            color: #1f2937;
            margin-top: 16px;
            margin-bottom: 6px;
        }

        /* Content */
        p {
            margin: 6px 0;
            text-align: justify;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 9pt;
        }
        th {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
        }
        td {
            border: 1px solid #d1d5db;
            padding: 5px 8px;
            vertical-align: top;
        }
        tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        /* Boxes */
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 9pt;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 9pt;
        }
        .danger-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 9pt;
        }
        .box-title {
            font-weight: bold;
            margin-bottom: 4px;
        }

        /* Code */
        code {
            background-color: #f3f4f6;
            padding: 1px 4px;
            border-radius: 3px;
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
        }

        /* Lists */
        ul, ol {
            margin: 6px 0;
            padding-left: 20px;
        }
        li {
            margin: 3px 0;
        }

        /* Example box */
        .example {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 10px 14px;
            margin: 10px 0;
            font-size: 9pt;
        }
        .example-title {
            font-weight: bold;
            color: #166534;
            margin-bottom: 6px;
        }

        .footer {
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
            margin-top: 40px;
        }
    </style>
</head>
<body>

{{-- =================== CAPA =================== --}}
<div class="cover">
    <h1>Mercury SaaS</h1>
    <h2>Manual de Administração do Painel Central</h2>
    <div class="meta">
        <p>Versão 1.0</p>
        <p>{{ now()->format('d/m/Y') }}</p>
        <p>Grupo Meia Sola</p>
    </div>
</div>

{{-- =================== SUMÁRIO =================== --}}
<div class="toc">
    <h2>Sumário</h2>
    <span class="toc-item">1. Introdução</span>
    <span class="toc-item">2. Acesso ao Painel Central</span>
    <span class="toc-item">3. Dashboard</span>
    <span class="toc-item">4. Gestão de Tenants</span>
    <span class="toc-item level2">4.1. Criar Tenant</span>
    <span class="toc-item level2">4.2. Detalhes do Tenant</span>
    <span class="toc-item level2">4.3. Roles Permitidas por Tenant</span>
    <span class="toc-item level2">4.4. Suspender / Reativar / Excluir</span>
    <span class="toc-item">5. Gestão de Planos</span>
    <span class="toc-item level2">5.1. Criar Plano</span>
    <span class="toc-item level2">5.2. Módulos do Plano</span>
    <span class="toc-item level2">5.3. Limites de Recursos</span>
    <span class="toc-item">6. Gestão de Módulos</span>
    <span class="toc-item level2">6.1. Criar Módulo</span>
    <span class="toc-item level2">6.2. Campos e Preenchimento</span>
    <span class="toc-item level2">6.3. Regras de Exclusão</span>
    <span class="toc-item">7. Navegação</span>
    <span class="toc-item level2">7.1. Menus</span>
    <span class="toc-item level2">7.2. Páginas</span>
    <span class="toc-item level2">7.3. Grupos de Páginas</span>
    <span class="toc-item level2">7.4. Permissões Padrão</span>
    <span class="toc-item">8. Roles & Permissões</span>
    <span class="toc-item level2">8.1. Roles do Sistema vs. Customizadas</span>
    <span class="toc-item level2">8.2. Criar Role Customizada</span>
    <span class="toc-item level2">8.3. Matriz de Permissões</span>
    <span class="toc-item level2">8.4. Gerenciar Permissões de uma Role</span>
    <span class="toc-item">9. Faturamento</span>
    <span class="toc-item level2">9.1. Indicadores (Cards)</span>
    <span class="toc-item level2">9.2. Criar Fatura Manual</span>
    <span class="toc-item level2">9.3. Gerar Fatura do Plano</span>
    <span class="toc-item level2">9.4. Geração em Lote</span>
    <span class="toc-item level2">9.5. Cobrar via Asaas</span>
    <span class="toc-item level2">9.6. Sincronizar com Asaas</span>
    <span class="toc-item level2">9.7. Confirmar Pagamento Manual</span>
    <span class="toc-item level2">9.8. Cancelar Fatura</span>
    <span class="toc-item level2">9.9. Configuração do Asaas</span>
    <span class="toc-item">10. Regras de Negócio</span>
    <span class="toc-item">11. Decisões Técnicas e Justificativas</span>
    <span class="toc-item level2">11.1. Gateway de Pagamento (Asaas)</span>
    <span class="toc-item level2">11.2. Multi-Tenancy (stancl/tenancy)</span>
    <span class="toc-item level2">11.3. Frontend (React + Inertia.js)</span>
    <span class="toc-item level2">11.4. Permissões (DB + Enum Fallback)</span>
    <span class="toc-item level2">11.5. Módulos em Banco de Dados</span>
    <span class="toc-item level2">11.6. Navegação Centralizada</span>
    <span class="toc-item level2">11.7. Roles por Tenant</span>
    <span class="toc-item">12. Glossário</span>
</div>

{{-- =================== 1. INTRODUÇÃO =================== --}}
<h1>1. Introdução</h1>

<p>O <strong>Mercury SaaS</strong> é uma plataforma multi-tenant de gestão empresarial. Este manual cobre o <strong>Painel Central de Administração</strong>, acessível exclusivamente pelos administradores do SaaS (você, o mantenedor da plataforma).</p>

<p>O painel central permite gerenciar todos os aspectos da plataforma sem necessidade de alterações em código:</p>

<ul>
    <li><strong>Tenants</strong> — empresas clientes que utilizam a plataforma</li>
    <li><strong>Planos</strong> — pacotes com limites de recursos e módulos</li>
    <li><strong>Módulos</strong> — funcionalidades da plataforma (Vendas, Produtos, RH, etc.)</li>
    <li><strong>Navegação</strong> — estrutura de menus e páginas da plataforma</li>
    <li><strong>Roles & Permissões</strong> — perfis de acesso e o que cada um pode fazer</li>
</ul>

<div class="info-box">
    <div class="box-title">Arquitetura Multi-Tenant</div>
    Cada tenant possui um banco de dados isolado. Alterações em um tenant não afetam outros. O banco central armazena as configurações da plataforma (planos, módulos, roles, navegação).
</div>

{{-- =================== 2. ACESSO =================== --}}
<h1>2. Acesso ao Painel Central</h1>

<p>O painel é acessado pelo domínio central da plataforma (sem subdomínio):</p>

<table>
    <tr>
        <th>Ambiente</th>
        <th>URL</th>
    </tr>
    <tr>
        <td>Desenvolvimento</td>
        <td><code>http://localhost:8000/login</code> ou <code>http://127.0.0.1:8000/login</code></td>
    </tr>
    <tr>
        <td>Produção</td>
        <td><code>https://mercury.com.br/login</code></td>
    </tr>
</table>

<p>As credenciais de acesso são do tipo <strong>CentralUser</strong>, separadas dos usuários dos tenants. O login utiliza e-mail e senha.</p>

<div class="warning-box">
    <div class="box-title">Importante</div>
    Os tenants acessam via subdomínio (ex: <code>empresa.mercury.com.br</code>). O login do painel central <strong>não funciona</strong> em subdomínios de tenant, e vice-versa.
</div>

<h2>Roles de Administrador Central</h2>

<table>
    <tr>
        <th>Role</th>
        <th>Pode fazer</th>
    </tr>
    <tr>
        <td><code>super_admin</code></td>
        <td>Acesso total: criar/editar/excluir tenants, planos, módulos, roles, navegação</td>
    </tr>
    <tr>
        <td><code>admin</code></td>
        <td>Gerenciar tenants e planos, sem acesso a configurações críticas</td>
    </tr>
    <tr>
        <td><code>viewer</code></td>
        <td>Apenas visualização, sem permissão de alterações</td>
    </tr>
</table>

{{-- =================== 3. DASHBOARD =================== --}}
<h1>3. Dashboard</h1>

<p>O dashboard (<code>/admin</code>) exibe um resumo da plataforma:</p>

<ul>
    <li><strong>Total de Tenants</strong> — quantos clientes estão cadastrados</li>
    <li><strong>Tenants Ativos / Inativos</strong> — status atual</li>
    <li><strong>Tenants em Trial</strong> — clientes no período de avaliação</li>
    <li><strong>Distribuição por Plano</strong> — quantos clientes em cada plano</li>
    <li><strong>Faturas Pendentes / Vencidas</strong> — controle financeiro</li>
    <li><strong>Receita Mensal</strong> — soma das faturas pagas no mês</li>
</ul>

{{-- =================== 4. TENANTS =================== --}}
<h1>4. Gestão de Tenants</h1>

<p>Acessível em <code>/admin/tenants</code>. Um <strong>tenant</strong> é uma empresa cliente que utiliza a plataforma. Cada tenant possui:</p>

<ul>
    <li>Banco de dados próprio (isolamento total)</li>
    <li>Subdomínio dedicado (ex: <code>meiasolavarejo.mercury.com.br</code>)</li>
    <li>Plano associado com limites e módulos</li>
    <li>Configurações independentes</li>
</ul>

<h2>4.1. Criar Tenant</h2>

<p>Clique no botão <strong>"Novo Tenant"</strong>. Preencha os campos:</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Obrigatório</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Nome da Empresa</td>
        <td>Sim</td>
        <td>Nome comercial do cliente</td>
        <td><code>Meia Sola Varejo</code></td>
    </tr>
    <tr>
        <td>Slug (URL)</td>
        <td>Não</td>
        <td>Identificador para o subdomínio. Auto-gerado se vazio. Apenas letras, números e hífens.</td>
        <td><code>meia-sola-varejo</code></td>
    </tr>
    <tr>
        <td>CNPJ</td>
        <td>Não</td>
        <td>CNPJ da empresa. Formatação automática.</td>
        <td><code>12.345.678/0001-90</code></td>
    </tr>
    <tr>
        <td>Nome do Responsável</td>
        <td>Sim</td>
        <td>Pessoa de contato principal</td>
        <td><code>João Silva</code></td>
    </tr>
    <tr>
        <td>E-mail do Responsável</td>
        <td>Sim</td>
        <td>E-mail que será o login do primeiro admin do tenant</td>
        <td><code>joao@meiasolavarejo.com.br</code></td>
    </tr>
    <tr>
        <td>Plano</td>
        <td>Não</td>
        <td>Plano inicial. Pode ser alterado depois.</td>
        <td><code>Professional</code></td>
    </tr>
    <tr>
        <td>Dias de Trial</td>
        <td>Não</td>
        <td>Período gratuito de avaliação (padrão: 30 dias)</td>
        <td><code>30</code></td>
    </tr>
    <tr>
        <td>Senha do Admin</td>
        <td>Não</td>
        <td>Senha do primeiro usuário admin do tenant. Auto-gerada se vazio.</td>
        <td><code>SenhaSegura123!</code></td>
    </tr>
</table>

<div class="info-box">
    <div class="box-title">O que acontece ao criar</div>
    O sistema automaticamente: cria o banco de dados do tenant, executa as migrations, semeia os dados iniciais (menus, páginas, permissões, dados de referência), cria o primeiro usuário admin, e registra o subdomínio.
</div>

<h2>4.2. Detalhes do Tenant</h2>

<p>Clique em um tenant para ver seus detalhes (<code>/admin/tenants/{id}</code>). A página exibe:</p>

<ul>
    <li><strong>Informações gerais</strong> — nome, slug, CNPJ, domínio, plano, responsável, data de criação</li>
    <li><strong>Módulos habilitados</strong> — lista dos módulos ativos no plano do tenant</li>
    <li><strong>Uso</strong> — quantidade de usuários, lojas e funcionários com barras de progresso vs. limites do plano</li>
    <li><strong>Roles permitidas</strong> — controle de quais perfis o admin do tenant pode criar</li>
    <li><strong>Alterar plano</strong> — dropdown para trocar o plano do tenant</li>
</ul>

<h2>4.3. Roles Permitidas por Tenant</h2>

<p>No card <strong>"Roles Permitidas"</strong> da página de detalhes, você define quais tipos de usuário o administrador do tenant pode criar.</p>

<div class="example">
    <div class="example-title">Exemplo de uso</div>
    <p>Um cliente no plano Starter não precisa de perfil "Suporte". Você desmarca "Suporte" nas roles permitidas. Quando o admin desse tenant criar um usuário, a opção "Suporte" não aparece no dropdown.</p>
</div>

<table>
    <tr>
        <th>Role</th>
        <th>Descrição</th>
        <th>Quando permitir</th>
    </tr>
    <tr>
        <td>Super Administrador</td>
        <td>Acesso total dentro do tenant</td>
        <td>Sempre (pelo menos 1 admin precisa existir)</td>
    </tr>
    <tr>
        <td>Administrador</td>
        <td>Gerencia tudo exceto alterar super admins</td>
        <td>Planos intermediários e acima</td>
    </tr>
    <tr>
        <td>Suporte</td>
        <td>Visualização geral, sem edição</td>
        <td>Planos com necessidade de suporte interno</td>
    </tr>
    <tr>
        <td>Usuário</td>
        <td>Apenas acesso ao próprio perfil e dashboard</td>
        <td>Sempre (nível básico)</td>
    </tr>
</table>

<div class="warning-box">
    <div class="box-title">Regra</div>
    Pelo menos uma role deve estar marcada. O sistema impede desmarcar todas.
</div>

<h2>4.4. Suspender / Reativar / Excluir</h2>

<table>
    <tr>
        <th>Ação</th>
        <th>Efeito</th>
        <th>Reversível?</th>
    </tr>
    <tr>
        <td><strong>Suspender</strong></td>
        <td>Usuários do tenant não conseguem mais acessar o sistema. Dados são preservados.</td>
        <td>Sim — use "Reativar"</td>
    </tr>
    <tr>
        <td><strong>Reativar</strong></td>
        <td>Restaura o acesso de um tenant suspenso.</td>
        <td>N/A</td>
    </tr>
    <tr>
        <td><strong>Excluir Permanentemente</strong></td>
        <td>Remove o tenant, o banco de dados e TODOS os dados. Requer confirmação.</td>
        <td><strong>NÃO</strong></td>
    </tr>
</table>

<div class="danger-box">
    <div class="box-title">ATENÇÃO</div>
    A exclusão permanente é irreversível. Todos os dados do tenant (usuários, vendas, funcionários, configurações) serão perdidos. Faça backup antes de excluir usando o comando <code>php artisan tenant:backup {id}</code>.
</div>

{{-- =================== 5. PLANOS =================== --}}
<h1>5. Gestão de Planos</h1>

<p>Acessível em <code>/admin/plans</code>. Planos definem os limites e funcionalidades disponíveis para cada tenant.</p>

<h2>5.1. Criar Plano</h2>

<table>
    <tr>
        <th>Campo</th>
        <th>Obrigatório</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Nome</td>
        <td>Sim</td>
        <td>Nome comercial do plano</td>
        <td><code>Professional</code></td>
    </tr>
    <tr>
        <td>Slug</td>
        <td>Sim</td>
        <td>Identificador único (não pode ser alterado após criação)</td>
        <td><code>professional</code></td>
    </tr>
    <tr>
        <td>Descrição</td>
        <td>Não</td>
        <td>Texto explicativo do plano</td>
        <td><code>Plano ideal para redes com até 10 lojas</code></td>
    </tr>
    <tr>
        <td>Máx Usuários</td>
        <td>Sim</td>
        <td>Limite de usuários. <code>0</code> = ilimitado.</td>
        <td><code>50</code></td>
    </tr>
    <tr>
        <td>Máx Lojas</td>
        <td>Sim</td>
        <td>Limite de lojas cadastradas. <code>0</code> = ilimitado.</td>
        <td><code>10</code></td>
    </tr>
    <tr>
        <td>Preço Mensal</td>
        <td>Sim</td>
        <td>Valor em reais (R$). Digitação tipo calculadora.</td>
        <td><code>R$ 499,90</code></td>
    </tr>
    <tr>
        <td>Preço Anual</td>
        <td>Sim</td>
        <td>Valor anual (geralmente com desconto)</td>
        <td><code>R$ 4.799,00</code></td>
    </tr>
    <tr>
        <td>Módulos</td>
        <td>Sim</td>
        <td>Quais módulos estão incluídos neste plano (checkboxes)</td>
        <td>Dashboard, Usuários, Vendas, Produtos, Lojas, etc.</td>
    </tr>
</table>

<h2>5.2. Módulos do Plano</h2>

<p>Na seção de módulos do formulário de plano, marque quais funcionalidades o plano inclui. Os módulos vêm da tabela central de módulos (gerenciada em <code>/admin/modules</code>).</p>

<div class="example">
    <div class="example-title">Exemplo de configuração de planos</div>
    <table>
        <tr>
            <th>Plano</th>
            <th>Usuários</th>
            <th>Lojas</th>
            <th>Módulos</th>
            <th>Preço</th>
        </tr>
        <tr>
            <td>Starter</td>
            <td>10</td>
            <td>1</td>
            <td>Dashboard, Usuários, Vendas, Configurações</td>
            <td>R$ 199,90</td>
        </tr>
        <tr>
            <td>Professional</td>
            <td>50</td>
            <td>10</td>
            <td>Todos exceto Integrações</td>
            <td>R$ 499,90</td>
        </tr>
        <tr>
            <td>Enterprise</td>
            <td>0 (ilimitado)</td>
            <td>0 (ilimitado)</td>
            <td>Todos</td>
            <td>R$ 999,90</td>
        </tr>
    </table>
</div>

<h2>5.3. Limites de Recursos</h2>

<p>Quando um tenant atinge o limite de usuários ou lojas do plano, o sistema bloqueia a criação de novos recursos com uma mensagem amigável. O middleware <code>CheckPlanLimit</code> verifica automaticamente antes de cada criação.</p>

<div class="info-box">
    <div class="box-title">Valor 0 = Ilimitado</div>
    Defina <code>0</code> em qualquer campo de limite para indicar que não há restrição.
</div>

{{-- =================== 6. MÓDULOS =================== --}}
<h1>6. Gestão de Módulos</h1>

<p>Acessível em <code>/admin/modules</code>. Módulos são as funcionalidades da plataforma. Cada módulo controla um conjunto de rotas e aparece como opção nos planos.</p>

<h2>6.1. Criar Módulo</h2>

<p>Clique em <strong>"Novo Módulo"</strong>. O formulário abre em um modal.</p>

<h2>6.2. Campos e Preenchimento</h2>

<table>
    <tr>
        <th>Campo</th>
        <th>Obrigatório</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Nome</td>
        <td>Sim</td>
        <td>Nome de exibição do módulo</td>
        <td><code>Vendas</code></td>
    </tr>
    <tr>
        <td>Slug</td>
        <td>Sim</td>
        <td>Identificador único. Apenas letras minúsculas, números e underscores. Não pode ser alterado após criação.</td>
        <td><code>sales</code></td>
    </tr>
    <tr>
        <td>Descrição</td>
        <td>Não</td>
        <td>Breve explicação da funcionalidade</td>
        <td><code>Registro e consulta de vendas por loja e período.</code></td>
    </tr>
    <tr>
        <td>Ícone</td>
        <td>Não</td>
        <td>Ícone da biblioteca Heroicons (outline). Selecionado via picker visual com busca.</td>
        <td><code>CurrencyDollarIcon</code></td>
    </tr>
    <tr>
        <td>Rotas</td>
        <td>Não</td>
        <td>Padrões de rota que o módulo controla. Use <code>*</code> como curinga. Digite e pressione Enter para adicionar.</td>
        <td><code>sales.*</code></td>
    </tr>
    <tr>
        <td>Dependências</td>
        <td>Não</td>
        <td>Slugs de módulos que devem estar ativos para este funcionar.</td>
        <td><code>stores</code> (transferências dependem de lojas)</td>
    </tr>
    <tr>
        <td>Módulo ativo</td>
        <td>—</td>
        <td>Apenas na edição. Módulos inativos não aparecem na lista de planos.</td>
        <td>—</td>
    </tr>
</table>

<div class="info-box">
    <div class="box-title">Ordem automática</div>
    A ordem de exibição dos módulos é definida automaticamente pelo sistema. Novos módulos são adicionados ao final.
</div>

<h2>6.3. Regras de Exclusão</h2>

<ul>
    <li>Um módulo <strong>só pode ser excluído</strong> se nenhum plano o utiliza</li>
    <li>Para remover um módulo em uso: primeiro desative-o nos planos, depois exclua</li>
    <li>Módulos inativos não são excluídos, apenas não aparecem como opção em novos planos</li>
</ul>

{{-- =================== 7. NAVEGAÇÃO =================== --}}
<h1>7. Navegação</h1>

<p>Acessível em <code>/admin/navigation</code>. Aqui você define a estrutura de menus e páginas que os tenants verão. A página possui 4 abas.</p>

<div class="warning-box">
    <div class="box-title">Afeta apenas novos tenants</div>
    Alterações na navegação central são usadas ao provisionar <strong>novos tenants</strong>. Tenants existentes mantêm sua estrutura atual. Para atualizar um tenant existente, é necessário executar o seeder manualmente.
</div>

<h2>7.1. Menus</h2>

<p>Menus são os itens do menu lateral da plataforma. Podem ter hierarquia (menu pai com submenus).</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Nome</td>
        <td>Texto exibido no menu lateral</td>
        <td><code>Comercial</code></td>
    </tr>
    <tr>
        <td>Ícone</td>
        <td>Ícone Font Awesome exibido ao lado do nome. Selecionável via picker visual.</td>
        <td><code>fas fa-money-bill-wave</code></td>
    </tr>
    <tr>
        <td>Tipo</td>
        <td>Categoria para agrupamento visual</td>
        <td><code>Principal</code>, <code>RH</code>, <code>Utilitário</code>, <code>Sistema</code></td>
    </tr>
    <tr>
        <td>Menu Pai</td>
        <td>Apenas na criação. Selecione para criar como submenu.</td>
        <td><code>Configurações</code></td>
    </tr>
</table>

<div class="example">
    <div class="example-title">Tipos de Menu</div>
    <table>
        <tr><th>Tipo</th><th>Uso</th><th>Exemplos</th></tr>
        <tr><td>Principal</td><td>Funcionalidades de negócio</td><td>Comercial, Financeiro, Produto</td></tr>
        <tr><td>RH</td><td>Recursos humanos</td><td>Departamento Pessoal, Pessoas & Cultura</td></tr>
        <tr><td>Utilitário</td><td>Ferramentas auxiliares</td><td>FAQ's, Biblioteca de Processos</td></tr>
        <tr><td>Sistema</td><td>Configurações e sistema</td><td>Configurações, Sair</td></tr>
    </table>
</div>

<h2>7.2. Páginas</h2>

<p>Páginas representam as telas da plataforma. Cada página pode estar vinculada a um módulo e a um grupo de ação.</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Nome</td>
        <td>Nome de exibição da página</td>
        <td><code>Vendas</code></td>
    </tr>
    <tr>
        <td>Rota</td>
        <td>Caminho Laravel da página (iniciando com <code>/</code>)</td>
        <td><code>/sales</code></td>
    </tr>
    <tr>
        <td>Ícone</td>
        <td>Ícone Font Awesome. Selecionável via picker.</td>
        <td><code>fas fa-chart-line</code></td>
    </tr>
    <tr>
        <td>Módulo</td>
        <td>Módulo ao qual a página pertence. Páginas sem módulo aparecem para todos os planos.</td>
        <td><code>Vendas</code></td>
    </tr>
    <tr>
        <td>Grupo</td>
        <td>Tipo de ação da página</td>
        <td><code>Listar</code></td>
    </tr>
    <tr>
        <td>Ativa</td>
        <td>Páginas inativas não são provisionadas</td>
        <td>—</td>
    </tr>
    <tr>
        <td>Pública</td>
        <td>Acessível sem login (ex: página de logout)</td>
        <td>—</td>
    </tr>
</table>

<h2>7.3. Grupos de Páginas</h2>

<p>Grupos categorizam as páginas por tipo de ação. São usados internamente para organização.</p>

<table>
    <tr><th>Grupo</th><th>Descrição</th></tr>
    <tr><td>Listar</td><td>Páginas de listagem/consulta</td></tr>
    <tr><td>Cadastrar</td><td>Páginas de criação de registros</td></tr>
    <tr><td>Editar</td><td>Páginas de edição de registros</td></tr>
    <tr><td>Apagar</td><td>Ações de exclusão</td></tr>
    <tr><td>Visualizar</td><td>Páginas de visualização detalhada</td></tr>
    <tr><td>Acesso</td><td>Páginas de controle de acesso</td></tr>
    <tr><td>Pesquisar</td><td>Páginas de busca</td></tr>
    <tr><td>Outros</td><td>Ações diversas</td></tr>
</table>

<h2>7.4. Permissões Padrão</h2>

<p>A aba <strong>"Permissões Padrão"</strong> exibe uma matriz de leitura que mostra quais páginas cada role pode acessar, agrupadas por menu. Essa configuração é usada ao provisionar novos tenants para definir as permissões iniciais.</p>

{{-- =================== 8. ROLES =================== --}}
<h1>8. Roles & Permissões</h1>

<p>Acessível em <code>/admin/roles</code>. Aqui você gerencia os perfis de acesso da plataforma e define o que cada perfil pode fazer.</p>

<h2>8.1. Roles do Sistema vs. Customizadas</h2>

<table>
    <tr>
        <th>Característica</th>
        <th>Role do Sistema</th>
        <th>Role Customizada</th>
    </tr>
    <tr>
        <td>Exemplos</td>
        <td>Super Administrador, Administrador, Suporte, Usuário</td>
        <td>Gerente de Loja, Supervisor, Auditor</td>
    </tr>
    <tr>
        <td>Pode excluir?</td>
        <td>Não</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Pode editar permissões?</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Pode alterar nível hierárquico?</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
</table>

<h2>8.2. Criar Role Customizada</h2>

<p>Clique em <strong>"Nova Role"</strong>.</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Slug</td>
        <td>Identificador único. Apenas letras minúsculas, números e underscores.</td>
        <td><code>gerente_loja</code></td>
    </tr>
    <tr>
        <td>Nome de Exibição</td>
        <td>Nome amigável mostrado no sistema</td>
        <td><code>Gerente de Loja</code></td>
    </tr>
    <tr>
        <td>Nível Hierárquico</td>
        <td>Define o poder da role. Roles com nível maior podem gerenciar roles com nível menor.</td>
        <td><code>2</code> (entre Usuário e Admin)</td>
    </tr>
</table>

<div class="example">
    <div class="example-title">Hierarquia de Níveis</div>
    <table>
        <tr><th>Nível</th><th>Role</th><th>Pode gerenciar</th></tr>
        <tr><td>4</td><td>Super Administrador</td><td>Todos</td></tr>
        <tr><td>3</td><td>Administrador</td><td>Suporte, Usuário e roles customizadas de nível inferior</td></tr>
        <tr><td>2</td><td>Suporte / Gerente de Loja</td><td>Apenas Usuário</td></tr>
        <tr><td>1</td><td>Usuário</td><td>Ninguém</td></tr>
    </table>
</div>

<h2>8.3. Matriz de Permissões</h2>

<p>Abaixo dos cards de roles, a <strong>Matriz de Permissões</strong> exibe uma tabela completa com todas as permissões agrupadas por categoria. Cada coluna representa uma role e mostra um check verde (&#10003;) quando a role possui aquela permissão.</p>

<p>As categorias de permissões são:</p>

<table>
    <tr><th>Categoria</th><th>Permissões</th></tr>
    <tr><td>Usuários</td><td>Visualizar, Criar, Editar, Deletar, Gerenciar roles</td></tr>
    <tr><td>Perfil</td><td>Ver/editar próprio perfil, ver/editar qualquer perfil</td></tr>
    <tr><td>Dashboard</td><td>Acessar dashboard</td></tr>
    <tr><td>Administração</td><td>Acessar painel admin</td></tr>
    <tr><td>Vendas</td><td>Visualizar, Criar, Editar, Deletar</td></tr>
    <tr><td>Produtos</td><td>Visualizar, Editar, Sincronizar</td></tr>
    <tr><td>Transferências</td><td>Visualizar, Criar, Editar, Deletar</td></tr>
    <tr><td>E mais 10+ categorias</td><td>Padrão CRUD (View, Create, Edit, Delete) por módulo</td></tr>
</table>

<h2>8.4. Gerenciar Permissões de uma Role</h2>

<p>Para editar as permissões de uma role, clique no botão com ícone de escudo no card da role. Abre um modal com:</p>

<ul>
    <li><strong>Contador</strong> — mostra quantas permissões estão selecionadas do total</li>
    <li><strong>Selecionar tudo / Limpar</strong> — atalhos rápidos</li>
    <li><strong>Checkbox por grupo</strong> — marca/desmarca todas as permissões de uma categoria</li>
    <li><strong>Estado indeterminado</strong> — o checkbox do grupo fica em estado "parcial" quando apenas algumas permissões estão marcadas</li>
    <li><strong>Descrição</strong> — cada permissão mostra uma descrição explicativa abaixo do nome</li>
</ul>

<div class="info-box">
    <div class="box-title">Cache de Permissões</div>
    As permissões são cacheadas por 5 minutos para performance. Após salvar alterações, o cache é automaticamente invalidado e as novas permissões entram em vigor imediatamente.
</div>

<div class="example">
    <div class="example-title">Exemplo: Criando role "Gerente de Loja"</div>
    <ol>
        <li>Clique em "Nova Role"</li>
        <li>Slug: <code>gerente_loja</code></li>
        <li>Nome: <code>Gerente de Loja</code></li>
        <li>Nível: <code>2</code></li>
        <li>Salve a role</li>
        <li>Clique no botão escudo da nova role</li>
        <li>Marque: Dashboard (acesso), Vendas (todas), Produtos (visualizar), Funcionários (visualizar), Metas de Loja (todas)</li>
        <li>Salve as permissões</li>
        <li>Vá em Tenants → selecione um tenant → Roles Permitidas → marque "Gerente de Loja"</li>
    </ol>
</div>

{{-- =================== 9. REGRAS =================== --}}
{{-- =================== 9. FATURAMENTO =================== --}}
<h1>9. Faturamento</h1>

<p>Acessível em <code>/admin/invoices</code>. O módulo de faturamento permite criar, gerenciar e cobrar faturas dos tenants. Integra com o gateway <strong>Asaas</strong> para cobranças via PIX, Boleto e Cartão de Crédito.</p>

<h2>9.1. Indicadores (Cards)</h2>

<p>No topo da página, quatro cards exibem os indicadores financeiros da plataforma:</p>

<table>
    <tr>
        <th>Card</th>
        <th>Descrição</th>
        <th>Cálculo</th>
    </tr>
    <tr>
        <td><strong>MRR</strong></td>
        <td>Receita Recorrente Mensal — indica quanto a plataforma gera por mês de forma previsível</td>
        <td>Soma das faturas mensais pagas no mês + faturas anuais pagas no mês divididas por 12</td>
    </tr>
    <tr>
        <td><strong>Pendentes</strong></td>
        <td>Aguardando pagamento — total em aberto</td>
        <td>Soma dos valores de faturas com status "pendente"</td>
    </tr>
    <tr>
        <td><strong>Vencidas</strong></td>
        <td>Pagamento em atraso</td>
        <td>Soma das faturas com status "vencido" ou pendentes com data de vencimento ultrapassada</td>
    </tr>
    <tr>
        <td><strong>Pagas este mês</strong></td>
        <td>Recebido no período atual</td>
        <td>Soma de todas as faturas pagas no mês corrente (independente do ciclo)</td>
    </tr>
</table>

<div class="example">
    <div class="example-title">Exemplo de MRR</div>
    <p>Se no mês atual foram pagas 3 faturas mensais de R$ 500 e 1 fatura anual de R$ 6.000:</p>
    <p><code>MRR = (3 × R$ 500) + (R$ 6.000 ÷ 12) = R$ 1.500 + R$ 500 = R$ 2.000</code></p>
</div>

<h2>9.2. Criar Fatura Manual</h2>

<p>Clique em <strong>"Nova Fatura"</strong>. Útil para cobranças avulsas, ajustes ou serviços extras.</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Obrigatório</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Tenant</td>
        <td>Sim</td>
        <td>Empresa que receberá a cobrança</td>
        <td><code>Meia Sola Varejo</code></td>
    </tr>
    <tr>
        <td>Ciclo</td>
        <td>Sim</td>
        <td>Mensal ou Anual</td>
        <td><code>Mensal</code></td>
    </tr>
    <tr>
        <td>Valor</td>
        <td>Sim</td>
        <td>Valor em reais. Digitação estilo calculadora (tecle os dígitos).</td>
        <td><code>R$ 499,90</code></td>
    </tr>
    <tr>
        <td>Início do Período</td>
        <td>Sim</td>
        <td>Data de início da cobertura da fatura</td>
        <td><code>01/04/2026</code></td>
    </tr>
    <tr>
        <td>Fim do Período</td>
        <td>Sim</td>
        <td>Data de fim da cobertura</td>
        <td><code>30/04/2026</code></td>
    </tr>
    <tr>
        <td>Vencimento</td>
        <td>Sim</td>
        <td>Data limite para pagamento</td>
        <td><code>10/04/2026</code></td>
    </tr>
    <tr>
        <td>Notas</td>
        <td>Não</td>
        <td>Observações internas (não visíveis ao tenant)</td>
        <td><code>Cobrança de setup inicial</code></td>
    </tr>
</table>

<h2>9.3. Gerar Fatura do Plano</h2>

<p>Na página de detalhes do tenant (<code>/admin/tenants/{id}</code>), o sistema pode gerar uma fatura automaticamente baseada no preço do plano associado. Para gerar via endpoint dedicado, use o botão <strong>"Gerar Fatura"</strong>.</p>

<p>O sistema automaticamente:</p>
<ul>
    <li>Lê o preço mensal ou anual do plano do tenant</li>
    <li>Define o período como o mês ou ano corrente</li>
    <li>Define o vencimento como 10 dias após o início do período</li>
    <li>Verifica se já existe fatura para o mesmo período (evita duplicatas)</li>
</ul>

<div class="warning-box">
    <div class="box-title">Requisitos</div>
    O tenant precisa ter um plano associado com preço definido para o ciclo selecionado. Tenants sem plano ou com preço R$ 0,00 não geram faturas.
</div>

<h2>9.4. Geração em Lote</h2>

<p>O botão <strong>"Gerar em Lote"</strong> cria faturas para todos os tenants ativos de uma vez. Selecione o ciclo (Mensal ou Anual) e o sistema:</p>

<ul>
    <li>Percorre todos os tenants ativos com plano</li>
    <li>Ignora tenants que já possuem fatura para o período</li>
    <li>Ignora tenants cujo plano não tem preço definido para o ciclo</li>
    <li>Cria faturas pendentes para os demais</li>
    <li>Exibe o resultado: "X faturas geradas, Y ignoradas"</li>
</ul>

<div class="example">
    <div class="example-title">Fluxo mensal recomendado</div>
    <ol>
        <li>No início de cada mês, acesse <code>/admin/invoices</code></li>
        <li>Clique em "Gerar em Lote" → Ciclo: Mensal</li>
        <li>Para cada fatura gerada, clique no botão roxo (Asaas) para criar a cobrança</li>
        <li>O tenant recebe o link de pagamento por e-mail automaticamente</li>
    </ol>
</div>

<h2>9.5. Cobrar via Asaas</h2>

<p>Para faturas pendentes sem cobrança no gateway, o botão roxo <strong>"Cobrar via Asaas"</strong> abre um modal com três opções:</p>

<table>
    <tr>
        <th>Tipo</th>
        <th>Descrição</th>
        <th>Como o tenant paga</th>
    </tr>
    <tr>
        <td><strong>PIX</strong></td>
        <td>Gera QR Code e código "copia e cola"</td>
        <td>Escaneia o QR Code ou cola o código no app do banco</td>
    </tr>
    <tr>
        <td><strong>Boleto</strong></td>
        <td>Gera boleto bancário</td>
        <td>Paga no banco, lotérica ou app bancário</td>
    </tr>
    <tr>
        <td><strong>Todos</strong></td>
        <td>O tenant escolhe o método na página de pagamento</td>
        <td>Acessa o link e escolhe PIX, Boleto ou Cartão</td>
    </tr>
</table>

<p>Ao criar a cobrança, o sistema:</p>
<ul>
    <li>Cria (ou atualiza) o cliente no Asaas com os dados do tenant (nome, e-mail, CNPJ)</li>
    <li>Gera a cobrança no gateway</li>
    <li>Salva o link de pagamento e o ID da cobrança na fatura</li>
    <li>O Asaas envia e-mail ao tenant com o link de pagamento</li>
</ul>

<div class="warning-box">
    <div class="box-title">CNPJ Obrigatório</div>
    O Asaas exige CPF ou CNPJ para criar cobranças. Certifique-se de que o tenant tem CNPJ cadastrado antes de cobrar. Edite os dados do tenant na página de detalhes.
</div>

<h2>9.6. Sincronizar com Asaas</h2>

<p>O botão ciano <strong>"Sincronizar"</strong> (ícone de seta circular) aparece em faturas que possuem cobrança no Asaas. Ao clicar, o sistema consulta o status da cobrança diretamente na API do Asaas e atualiza a fatura:</p>

<table>
    <tr>
        <th>Status no Asaas</th>
        <th>Ação no sistema</th>
    </tr>
    <tr>
        <td>RECEIVED / CONFIRMED</td>
        <td>Fatura marcada como <strong>paga</strong> com método e data do pagamento</td>
    </tr>
    <tr>
        <td>OVERDUE</td>
        <td>Fatura marcada como <strong>vencida</strong></td>
    </tr>
    <tr>
        <td>DELETED / REFUNDED</td>
        <td>Fatura marcada como <strong>cancelada</strong></td>
    </tr>
    <tr>
        <td>PENDING</td>
        <td>Nenhuma alteração (ainda aguardando pagamento)</td>
    </tr>
</table>

<div class="info-box">
    <div class="box-title">Quando usar</div>
    Em ambiente de desenvolvimento (sem webhook), use este botão para verificar se o pagamento foi confirmado. Em produção, o webhook atualiza automaticamente — mas o botão serve como fallback caso o webhook falhe.
</div>

<h2>9.7. Confirmar Pagamento Manual</h2>

<p>O botão verde <strong>"Marcar como pago"</strong> permite confirmar o pagamento manualmente, sem passar pelo Asaas. Útil para pagamentos recebidos fora do gateway (transferência direta, dinheiro, etc.).</p>

<table>
    <tr>
        <th>Campo</th>
        <th>Obrigatório</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td>Método de Pagamento</td>
        <td>Sim</td>
        <td>Como o pagamento foi recebido</td>
        <td><code>PIX</code>, <code>Boleto</code>, <code>Cartão</code>, <code>Transferência</code>, <code>Dinheiro</code>, <code>Outro</code></td>
    </tr>
    <tr>
        <td>Data do Pagamento</td>
        <td>Não</td>
        <td>Data em que o pagamento foi recebido (padrão: hoje)</td>
        <td><code>08/04/2026</code></td>
    </tr>
    <tr>
        <td>ID da Transação</td>
        <td>Não</td>
        <td>Identificador do pagamento no banco ou comprovante</td>
        <td><code>E12345678202604081234</code></td>
    </tr>
</table>

<div class="warning-box">
    <div class="box-title">Importante</div>
    A confirmação manual <strong>não notifica o Asaas</strong>. Se a fatura tiver cobrança no gateway, o status pode ficar divergente. Use a confirmação manual apenas para pagamentos recebidos fora do Asaas.
</div>

<h2>9.8. Cancelar Fatura</h2>

<p>O botão vermelho cancela a fatura. Regras:</p>

<ul>
    <li>Faturas <strong>pendentes</strong> e <strong>vencidas</strong> podem ser canceladas</li>
    <li>Faturas <strong>pagas</strong> não podem ser canceladas (estorne pelo Asaas se necessário)</li>
    <li>O cancelamento é definitivo</li>
    <li>Se a fatura tem cobrança no Asaas, o cancelamento local <strong>não cancela no Asaas</strong> automaticamente</li>
</ul>

<h2>9.9. Configuração do Asaas</h2>

<p>Para utilizar as funcionalidades de cobrança, configure as variáveis de ambiente no arquivo <code>.env</code>:</p>

<table>
    <tr>
        <th>Variável</th>
        <th>Descrição</th>
        <th>Exemplo</th>
    </tr>
    <tr>
        <td><code>ASAAS_API_KEY</code></td>
        <td>Chave da API obtida no painel do Asaas (Integrações > API Keys)</td>
        <td><code>$aact_YTU5YTE...</code></td>
    </tr>
    <tr>
        <td><code>ASAAS_BASE_URL</code></td>
        <td>URL base da API. Sandbox para testes, produção para ambiente real.</td>
        <td>Sandbox: <code>https://sandbox.asaas.com/api/v3</code><br>Produção: <code>https://api.asaas.com/api/v3</code></td>
    </tr>
    <tr>
        <td><code>ASAAS_WEBHOOK_TOKEN</code></td>
        <td>Token de autenticação para o webhook. Deve ser o mesmo cadastrado no painel do Asaas.</td>
        <td><code>meu_token_secreto_123</code></td>
    </tr>
</table>

<p><strong>Webhook (produção):</strong> Cadastre a URL <code>https://seudominio.com.br/api/asaas/webhook</code> no painel do Asaas em Configurações > Integrações > Webhooks. O Auth Token deve ser o mesmo valor de <code>ASAAS_WEBHOOK_TOKEN</code>.</p>

<div class="info-box">
    <div class="box-title">Sem Asaas configurado</div>
    Se <code>ASAAS_API_KEY</code> não estiver definido, os botões de cobrança Asaas não aparecem. Todo o módulo de faturamento funciona normalmente para gestão manual (criar faturas, marcar como pago, cancelar).
</div>

<h3>Botões de Ação — Referência Rápida</h3>

<table>
    <tr>
        <th>Cor</th>
        <th>Ação</th>
        <th>Quando aparece</th>
    </tr>
    <tr>
        <td style="background-color: #e0e7ff; color: #4338ca; padding: 4px 8px; font-weight: bold;">Indigo</td>
        <td>Ver detalhes</td>
        <td>Sempre</td>
    </tr>
    <tr>
        <td style="background-color: #fef3c7; color: #92400e; padding: 4px 8px; font-weight: bold;">Amber</td>
        <td>Editar fatura</td>
        <td>Pendente ou Vencida</td>
    </tr>
    <tr>
        <td style="background-color: #f3e8ff; color: #7c3aed; padding: 4px 8px; font-weight: bold;">Roxo</td>
        <td>Cobrar via Asaas</td>
        <td>Pendente, sem cobrança, Asaas configurado</td>
    </tr>
    <tr>
        <td style="background-color: #cffafe; color: #0e7490; padding: 4px 8px; font-weight: bold;">Ciano</td>
        <td>Sincronizar com Asaas</td>
        <td>Pendente/Vencida, com cobrança</td>
    </tr>
    <tr>
        <td style="background-color: #dcfce7; color: #166534; padding: 4px 8px; font-weight: bold;">Verde</td>
        <td>Marcar como pago</td>
        <td>Pendente ou Vencida</td>
    </tr>
    <tr>
        <td style="background-color: #fee2e2; color: #991b1b; padding: 4px 8px; font-weight: bold;">Vermelho</td>
        <td>Cancelar fatura</td>
        <td>Pendente ou Vencida</td>
    </tr>
</table>

{{-- =================== 10. REGRAS =================== --}}
<h1>10. Regras de Negócio</h1>

<h2>Provisionamento de Tenants</h2>
<ul>
    <li>Ao criar um tenant, o sistema cria o banco de dados, executa migrations, e semeia os dados iniciais</li>
    <li>Se a navegação central estiver populada, os menus/páginas são copiados da central; caso contrário, usa seeders estáticos</li>
    <li>Páginas são filtradas pelos módulos ativos do plano do tenant</li>
    <li>Permissões padrão da central são mapeadas para os access_levels do tenant</li>
</ul>

<h2>Isolamento de Dados</h2>
<ul>
    <li>Cada tenant tem banco de dados independente (prefixo <code>mercury_</code>)</li>
    <li>Alterações em um tenant jamais afetam outro</li>
    <li>O banco central armazena apenas configurações da plataforma</li>
</ul>

<h2>Limites e Enforcement</h2>
<ul>
    <li>Limites de usuários/lojas são verificados via middleware <code>CheckPlanLimit</code></li>
    <li>Módulos são verificados via middleware <code>CheckTenantModule</code></li>
    <li>Tenants suspensos ou com trial expirado são bloqueados via <code>CheckTenantActive</code></li>
</ul>

<h2>Hierarquia de Roles</h2>
<ul>
    <li>Um usuário só pode gerenciar roles com nível hierárquico <strong>inferior</strong> ao seu</li>
    <li>Super Admin (nível 4) pode gerenciar todos</li>
    <li>Admin (nível 3) não pode alterar Super Admins</li>
    <li>Roles customizadas seguem a mesma lógica baseada no nível</li>
</ul>

<h2>Backward Compatibility</h2>
<ul>
    <li>Se as tabelas centrais estiverem vazias, o sistema usa os enums PHP como fallback</li>
    <li>Tenants existentes não são afetados por alterações na navegação central</li>
    <li>O cache de permissões (5min) garante performance sem consultas repetidas ao banco central</li>
</ul>

{{-- =================== 10. GLOSSÁRIO =================== --}}
{{-- =================== 11. DECISÕES TÉCNICAS =================== --}}
<h1>11. Decisões Técnicas e Justificativas</h1>

<p>Este capítulo documenta os motivos por trás das escolhas técnicas feitas na construção da plataforma. Serve como referência para decisões futuras e para entender o raciocínio por trás de cada componente.</p>

<h2>11.1. Gateway de Pagamento — Asaas</h2>

<p><strong>Escolhido:</strong> Asaas &nbsp; | &nbsp; <strong>Alternativas avaliadas:</strong> Stripe, iugu, Vindi, Efí (Gerencianet), PagSeguro</p>

<table>
    <tr>
        <th>Critério</th>
        <th>Asaas</th>
        <th>Stripe</th>
        <th>iugu</th>
    </tr>
    <tr>
        <td>PIX nativo</td>
        <td><strong>Sim</strong> — QR Code + copia e cola via API</td>
        <td>Sim (limitado no BR)</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Boleto</td>
        <td><strong>Sim</strong> — geração automática</td>
        <td>Não</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Cartão recorrente</td>
        <td><strong>Sim</strong> — assinaturas nativas</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>NFSe integrada</td>
        <td><strong>Sim</strong> — emissão automática</td>
        <td>Não</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Webhook</td>
        <td><strong>Sim</strong> — completo com auth token</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Sandbox</td>
        <td><strong>Sim</strong> — ambiente completo de testes</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>API em português</td>
        <td><strong>Sim</strong></td>
        <td>Não</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Foco no mercado BR</td>
        <td><strong>Sim</strong> — especialista em SaaS brasileiro</td>
        <td>Internacional</td>
        <td>Sim</td>
    </tr>
</table>

<p><strong>Motivos da escolha:</strong></p>
<ul>
    <li><strong>Cobertura completa de meios de pagamento brasileiros</strong> — PIX, Boleto e Cartão em uma única integração. Não é necessário integrar múltiplos gateways.</li>
    <li><strong>NFSe embutida</strong> — obrigação fiscal para SaaS no Brasil. O Asaas emite automaticamente, eliminando a necessidade de integrar com prefeituras individualmente.</li>
    <li><strong>API REST simples</strong> — não requer SDK proprietário. Integração via HTTP puro com o <code>Http</code> facade do Laravel, sem dependências externas.</li>
    <li><strong>Cobrança recorrente nativa</strong> — suporta assinaturas com ciclos mensal, trimestral, semestral e anual, eliminando a necessidade de schedulers próprios para gerar cobranças.</li>
    <li><strong>Dunning automático</strong> — retentativas de cobrança em cartão e envio de lembretes de pagamento são gerenciados pelo próprio Asaas.</li>
    <li><strong>Custo competitivo</strong> — taxas adequadas para o modelo SaaS B2B brasileiro.</li>
</ul>

<div class="info-box">
    <div class="box-title">Decisão de integração direta (sem SDK)</div>
    Optamos por usar o <code>Http</code> facade do Laravel em vez de SDKs de terceiros (<code>codephix/asaas-sdk</code>, etc.) por três motivos: (1) controle total sobre as chamadas, (2) sem dependência de manutenção de pacotes externos, (3) a API REST do Asaas é simples o suficiente para um wrapper de ~150 linhas.
</div>

<h2>11.2. Multi-Tenancy — stancl/tenancy</h2>

<p><strong>Escolhido:</strong> stancl/tenancy v3 (database-per-tenant) &nbsp; | &nbsp; <strong>Alternativas:</strong> tenancy nativa com scopes, spatie/laravel-multitenancy, single-database com tenant_id</p>

<p><strong>Motivos da escolha:</strong></p>
<ul>
    <li><strong>Isolamento total de dados</strong> — cada tenant tem seu próprio banco de dados MySQL. Impossível um tenant acessar dados de outro acidentalmente. Fundamental para compliance (LGPD) e confiança dos clientes.</li>
    <li><strong>Simplicidade de backup/restore</strong> — backup por tenant é um simples <code>mysqldump</code>. Restaurar um tenant específico não afeta os demais.</li>
    <li><strong>Performance</strong> — sem necessidade de filtrar por <code>tenant_id</code> em todas as queries. Cada banco é otimizado para o volume do tenant.</li>
    <li><strong>Migração facilitada</strong> — o sistema Mercury original já operava com um banco por empresa. O modelo database-per-tenant preservou essa arquitetura.</li>
    <li><strong>Pacote maduro</strong> — stancl/tenancy é o pacote mais popular para multi-tenancy em Laravel, com documentação extensa e comunidade ativa.</li>
    <li><strong>Identificação por subdomínio</strong> — cada tenant acessa via <code>empresa.mercury.com.br</code>, separando naturalmente o tráfego central do de tenants.</li>
</ul>

<div class="warning-box">
    <div class="box-title">Trade-off</div>
    Database-per-tenant consome mais recursos (uma conexão MySQL por tenant ativo). Para centenas de tenants, é necessário monitorar o número de conexões do MySQL e considerar connection pooling.
</div>

<h2>11.3. Frontend — React + Inertia.js</h2>

<p><strong>Escolhido:</strong> React 18 + Inertia.js 2 &nbsp; | &nbsp; <strong>Alternativas:</strong> Blade + Livewire, Vue.js + Inertia, API REST + SPA separada</p>

<p><strong>Motivos da escolha:</strong></p>
<ul>
    <li><strong>Sem API separada</strong> — Inertia.js permite que controllers Laravel renderizem diretamente para componentes React, eliminando a necessidade de construir e manter uma API REST. Reduz drasticamente o código de infraestrutura.</li>
    <li><strong>Experiência SPA</strong> — navegação sem reload de página, transições suaves, estados mantidos entre páginas. A experiência do usuário é de um aplicativo moderno.</li>
    <li><strong>Ecossistema React</strong> — maior biblioteca de componentes, maior mercado de desenvolvedores, Heroicons, Tailwind CSS com excelente suporte.</li>
    <li><strong>RBAC no frontend</strong> — o hook <code>usePermissions()</code> espelha a lógica de permissões do backend, permitindo esconder/mostrar elementos da UI de forma consistente.</li>
    <li><strong>Formulários via useForm</strong> — Inertia.js gerencia estado de formulários, validação, CSRF e erros do servidor automaticamente.</li>
</ul>

<h2>11.4. Permissões — DB com Fallback para Enum</h2>

<p><strong>Escolhido:</strong> CentralRoleResolver (DB + cache + fallback) &nbsp; | &nbsp; <strong>Alternativas:</strong> enums PHP puros, spatie/laravel-permission, políticas do Laravel Gate</p>

<p><strong>Motivos da escolha:</strong></p>
<ul>
    <li><strong>Flexibilidade sem deploy</strong> — o admin SaaS pode criar roles customizadas e alterar permissões via painel, sem alterar código ou fazer deploy.</li>
    <li><strong>Backward compatible</strong> — se as tabelas centrais estiverem vazias, o sistema usa os enums PHP como fallback. Zero risco na migração.</li>
    <li><strong>Performance via cache</strong> — permissões são cacheadas por 5 minutos (cache store file). Sem consulta ao banco central a cada requisição.</li>
    <li><strong>Sem dependência externa</strong> — não usamos spatie/laravel-permission porque o modelo de hierarquia (nível numérico) e a relação com multi-tenancy exigiam customização que seria difícil com o pacote genérico.</li>
    <li><strong>Hierarquia numérica</strong> — roles têm um <code>hierarchy_level</code> que determina quem pode gerenciar quem. Mais simples e intuitivo que árvores de permissão complexas.</li>
</ul>

<h2>11.5. Módulos em Banco de Dados</h2>

<p><strong>Escolhido:</strong> Tabela <code>central_modules</code> &nbsp; | &nbsp; <strong>Alternativa anterior:</strong> <code>config/modules.php</code> (arquivo estático)</p>

<p><strong>Motivos da mudança:</strong></p>
<ul>
    <li><strong>CRUD sem deploy</strong> — novos módulos podem ser criados, editados e ativados/desativados pelo admin SaaS sem alterar código.</li>
    <li><strong>Vinculação com planos</strong> — a tabela <code>tenant_modules</code> referencia módulos por slug. Com a tabela central, módulos novos aparecem automaticamente na tela de planos.</li>
    <li><strong>Metadados ricos</strong> — cada módulo armazena rotas, dependências, ícone e descrição no banco, acessíveis por qualquer parte do sistema.</li>
    <li><strong>Desativação global</strong> — um módulo desativado na central desaparece de todos os planos imediatamente, sem precisar editar cada plano individualmente.</li>
</ul>

<h2>11.6. Navegação Centralizada</h2>

<p><strong>Escolhido:</strong> Tabelas centrais (<code>central_menus</code>, <code>central_pages</code>) &nbsp; | &nbsp; <strong>Alternativa anterior:</strong> Seeders estáticos por tenant</p>

<p><strong>Motivos da mudança:</strong></p>
<ul>
    <li><strong>Estrutura da plataforma, não do tenant</strong> — menus e páginas são definidos pelo mantenedor do SaaS, não pelo admin do tenant. A navegação é parte do produto, não configuração do cliente.</li>
    <li><strong>Consistência entre tenants</strong> — todos os novos tenants recebem a mesma estrutura de navegação, filtrada pelos módulos do plano.</li>
    <li><strong>Atualizações centralizadas</strong> — quando um novo módulo é criado, as páginas correspondentes são adicionadas na central e novos tenants já recebem a navegação atualizada.</li>
    <li><strong>Permissões padrão por role</strong> — a tabela <code>central_menu_page_defaults</code> define quais páginas cada role acessa por padrão, garantindo que novos tenants já têm permissões corretas.</li>
</ul>

<div class="info-box">
    <div class="box-title">Tenants existentes</div>
    Alterações na navegação central afetam apenas novos tenants. Tenants existentes mantêm sua estrutura atual. Isso é intencional — evita quebras em clientes que já personalizaram suas configurações.
</div>

<h2>11.7. Roles por Tenant (allowed_roles)</h2>

<p><strong>Escolhido:</strong> Campo <code>settings.allowed_roles</code> no JSON do tenant &nbsp; | &nbsp; <strong>Alternativa:</strong> allowed_roles por plano</p>

<p><strong>Motivos da escolha:</strong></p>
<ul>
    <li><strong>Granularidade por tenant</strong> — permite restringir roles individualmente. Um tenant enterprise pode ter todas as roles; um starter pode ter apenas "admin" e "user".</li>
    <li><strong>Sem migration adicional</strong> — utiliza o campo <code>settings</code> JSON que já existia na tabela <code>tenants</code>. Zero impacto no schema.</li>
    <li><strong>Validação em dois níveis</strong> — o backend valida a role tanto no dropdown (frontend filtra) quanto na criação do usuário (backend rejeita roles não permitidas). Mesmo que o frontend seja burlado, a API protege.</li>
    <li><strong>Backward compatible</strong> — tenants sem <code>allowed_roles</code> definido veem todas as roles disponíveis. Nenhum tenant existente é afetado.</li>
    <li><strong>Flexibilidade comercial</strong> — permite usar roles como alavanca de upsell. "Quer perfil Suporte? Upgrade para o plano Professional."</li>
</ul>

<div class="example">
    <div class="example-title">Por que não por plano?</div>
    <p>A alternativa de vincular <code>allowed_roles</code> ao plano (e não ao tenant) foi considerada. Seria mais fácil de gerenciar para muitos tenants, mas menos flexível: se dois tenants no mesmo plano precisam de roles diferentes, seria necessário criar planos duplicados. A abordagem por tenant permite exceções sem duplicação.</p>
</div>

{{-- =================== 12. GLOSSÁRIO =================== --}}
<h1>12. Glossário</h1>

<table>
    <tr><th>Termo</th><th>Definição</th></tr>
    <tr><td><strong>Tenant</strong></td><td>Empresa cliente que utiliza a plataforma. Cada tenant tem seu próprio banco de dados e subdomínio.</td></tr>
    <tr><td><strong>Plano</strong></td><td>Pacote de funcionalidades e limites de recursos atribuído a um tenant.</td></tr>
    <tr><td><strong>Módulo</strong></td><td>Funcionalidade da plataforma (ex: Vendas, Produtos, RH). Pode ser ativado/desativado por plano.</td></tr>
    <tr><td><strong>Role</strong></td><td>Perfil de acesso que define o que um usuário pode fazer dentro de um tenant.</td></tr>
    <tr><td><strong>Permissão</strong></td><td>Autorização granular para uma ação específica (ex: "Criar vendas", "Visualizar produtos").</td></tr>
    <tr><td><strong>Hierarquia</strong></td><td>Nível numérico de uma role que define quem pode gerenciar quem.</td></tr>
    <tr><td><strong>Slug</strong></td><td>Identificador único em formato URL-friendly (letras minúsculas, números, hífens/underscores).</td></tr>
    <tr><td><strong>Trial</strong></td><td>Período de avaliação gratuita de um tenant antes da cobrança.</td></tr>
    <tr><td><strong>Provisioning</strong></td><td>Processo automático de criação de banco de dados e dados iniciais para um novo tenant.</td></tr>
    <tr><td><strong>Middleware</strong></td><td>Camada de verificação que intercepta requisições para validar permissões, limites, etc.</td></tr>
    <tr><td><strong>Central</strong></td><td>Domínio principal da plataforma onde o painel de administração opera.</td></tr>
    <tr><td><strong>Subdomínio</strong></td><td>Endereço do tenant (ex: empresa.mercury.com.br).</td></tr>
    <tr><td><strong>MRR</strong></td><td>Monthly Recurring Revenue — receita recorrente mensal. Métrica principal de saúde financeira de um SaaS.</td></tr>
    <tr><td><strong>Gateway</strong></td><td>Plataforma de pagamento que processa cobranças (ex: Asaas, Stripe). Gera boletos, PIX e processa cartões.</td></tr>
    <tr><td><strong>Webhook</strong></td><td>Notificação automática enviada pelo gateway quando o status de um pagamento muda (confirmado, vencido, etc.).</td></tr>
    <tr><td><strong>Fatura</strong></td><td>Registro de cobrança associado a um tenant e período. Pode ser manual ou gerada a partir do plano.</td></tr>
    <tr><td><strong>Asaas</strong></td><td>Gateway de pagamento brasileiro utilizado para cobranças via PIX, Boleto e Cartão de Crédito.</td></tr>
</table>

<div class="footer">
    <p>Mercury SaaS — Manual de Administração v1.2 — {{ now()->format('d/m/Y') }}</p>
    <p>Documento gerado automaticamente. Grupo Meia Sola.</p>
</div>

</body>
</html>
