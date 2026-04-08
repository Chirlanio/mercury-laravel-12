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
    <span class="toc-item">9. Regras de Negócio</span>
    <span class="toc-item">10. Glossário</span>
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
<h1>9. Regras de Negócio</h1>

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
<h1>10. Glossário</h1>

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
</table>

<div class="footer">
    <p>Mercury SaaS — Manual de Administração v1.0 — {{ now()->format('d/m/Y') }}</p>
    <p>Documento gerado automaticamente. Grupo Meia Sola.</p>
</div>

</body>
</html>
