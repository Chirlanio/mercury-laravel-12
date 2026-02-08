-- ============================================================================
-- Registro de Rotas - Módulo Service Orders (Ordens de Serviço)
-- Data: 2025-12-31
-- Descrição: Registra todas as páginas e endpoints do módulo de Service Orders
-- ============================================================================

USE u401878354_meiaso26_bd_me;

-- ============================================================================
-- 1. PÁGINA PRINCIPAL (Listagem)
-- ============================================================================

INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    560,
    'ServiceOrder',
    'list',
    'service-order',
    'list',
    'Ordens de Serviço',
    '<p>Listagem de Ordens de Serviço (Qualidade)</p>',
    2,
    'fas fa-clipboard-list',
    1, -- Listar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 2. CRUD ACTIONS
-- ============================================================================

-- 2.1 Adicionar Ordem de Serviço
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    561,
    'AddServiceOrder',
    'create',
    'add-service-order',
    'create',
    'Cadastrar Ordem de Serviço',
    '<p>Cadastrar nova Ordem de Serviço</p>',
    2,
    'fas fa-plus',
    2, -- Cadastrar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 2.2 Visualizar Ordem de Serviço
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    562,
    'ViewServiceOrder',
    'view',
    'view-service-order',
    'view',
    'Visualizar Ordem de Serviço',
    '<p>Visualizar detalhes da Ordem de Serviço</p>',
    2,
    'fas fa-eye',
    5, -- Visualizar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 2.3 Editar Ordem de Serviço (GET - Carregar formulário)
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    563,
    'EditServiceOrder',
    'edit',
    'edit-service-order',
    'edit',
    'Editar Ordem de Serviço',
    '<p>Editar Ordem de Serviço existente</p>',
    2,
    'fas fa-edit',
    3, -- Editar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 2.4 Editar Ordem de Serviço (POST - Salvar alterações)
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    564,
    'EditServiceOrder',
    'update',
    'edit-service-order',
    'update',
    'Atualizar Ordem de Serviço',
    '<p>Processar atualização da Ordem de Serviço</p>',
    2,
    'fas fa-save',
    3, -- Editar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 2.5 Deletar Ordem de Serviço
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    565,
    'DeleteServiceOrder',
    'delete',
    'delete-service-order',
    'delete',
    'Apagar Ordem de Serviço',
    '<p>Apagar Ordem de Serviço (Soft Delete)</p>',
    2,
    'fas fa-trash',
    4, -- Apagar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 3. UPLOAD DE IMAGENS
-- ============================================================================

INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    566,
    'UploadServiceOrderImages',
    'upload',
    'upload-service-order-images',
    'upload',
    'Upload de Imagens - Ordem de Serviço',
    '<p>Upload das 4 imagens obrigatórias da Ordem de Serviço</p>',
    2,
    'fas fa-upload',
    6, -- Outros
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 4. KANBAN BOARD
-- ============================================================================

INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    567,
    'ServiceOrderKanban',
    'kanban',
    'service-order-kanban',
    'kanban',
    'Kanban - Ordens de Serviço',
    '<p>Visualização em Kanban Board com drag & drop</p>',
    2,
    'fas fa-columns',
    1, -- Listar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 4.1 Update Status via Kanban (AJAX)
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    568,
    'EditServiceOrder',
    'updateStatus',
    'edit-service-order',
    'update-status',
    'Atualizar Status - Kanban',
    '<p>Endpoint AJAX para atualizar status via drag & drop do Kanban</p>',
    2,
    'fas fa-exchange-alt',
    6, -- Outros
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 5. DASHBOARD EXECUTIVO
-- ============================================================================

-- 5.1 Dashboard Principal
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    569,
    'DashboardServiceOrders',
    'dashboard',
    'dashboard-service-orders',
    'dashboard',
    'Dashboard Executivo - Ordens de Serviço',
    '<p>Dashboard executivo com gráficos e métricas</p>',
    2,
    'fas fa-chart-line',
    1, -- Listar
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- 5.2 Get Chart Data (AJAX)
INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    570,
    'DashboardServiceOrders',
    'getChartData',
    'dashboard-service-orders',
    'get-chart-data',
    'Dados para Gráficos - Dashboard',
    '<p>Endpoint AJAX que retorna JSON com dados para os gráficos Chart.js</p>',
    2,
    'fas fa-chart-area',
    6, -- Outros
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 6. ESTATÍSTICAS (AJAX)
-- ============================================================================

INSERT INTO adms_paginas (
    id, controller, metodo, menu_controller, menu_metodo,
    nome_pagina, obs, lib_pub, icone,
    adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id,
    created, modified
) VALUES (
    571,
    'ServiceOrder',
    'getStatistics',
    'service-order',
    'get-statistics',
    'Estatísticas - Ordens de Serviço',
    '<p>Endpoint AJAX para retornar estatísticas filtradas (9 cards por status)</p>',
    2,
    'fas fa-chart-bar',
    6, -- Outros
    1, -- Administrativo
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- 7. PERMISSÕES (adms_nivacs_pgs)
-- ============================================================================
-- Associa páginas aos níveis de acesso
-- Níveis: 1=Super Admin, 2=Admin, 18=Loja
-- Campos: permissao (1=tem acesso), ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id

-- 7.1 Página Principal (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 560, 0, 1, 26, 1, 560, NOW(), NULL), -- Super Admin
    (1, 560, 0, 1, 26, 2, 560, NOW(), NULL), -- Admin
    (1, 560, 0, 1, 26, 18, 560, NOW(), NULL); -- Loja

-- 7.2 Adicionar (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 561, 0, 0, 0, 1, 561, NOW(), NULL), -- Super Admin
    (1, 561, 0, 0, 0, 2, 561, NOW(), NULL), -- Admin
    (1, 561, 0, 0, 0, 18, 561, NOW(), NULL); -- Loja

-- 7.3 Visualizar (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 562, 0, 0, 0, 1, 562, NOW(), NULL), -- Super Admin
    (1, 562, 0, 0, 0, 2, 562, NOW(), NULL), -- Admin
    (1, 562, 0, 0, 0, 18, 562, NOW(), NULL); -- Loja

-- 7.4 Editar (GET) (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 563, 0, 0, 0, 1, 563, NOW(), NULL), -- Super Admin
    (1, 563, 0, 0, 0, 2, 563, NOW(), NULL), -- Admin
    (1, 563, 0, 0, 0, 18, 563, NOW(), NULL); -- Loja

-- 7.5 Editar (POST) (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 564, 0, 0, 0, 1, 564, NOW(), NULL), -- Super Admin
    (1, 564, 0, 0, 0, 2, 564, NOW(), NULL), -- Admin
    (1, 564, 0, 0, 0, 18, 564, NOW(), NULL); -- Loja

-- 7.6 Deletar (Apenas Admin e Super Admin)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 565, 0, 0, 0, 1, 565, NOW(), NULL), -- Super Admin
    (1, 565, 0, 0, 0, 2, 565, NOW(), NULL); -- Admin

-- 7.7 Upload Imagens (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 566, 0, 0, 0, 1, 566, NOW(), NULL), -- Super Admin
    (1, 566, 0, 0, 0, 2, 566, NOW(), NULL), -- Admin
    (1, 566, 0, 0, 0, 18, 566, NOW(), NULL); -- Loja

-- 7.8 Kanban (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 567, 0, 0, 0, 1, 567, NOW(), NULL), -- Super Admin
    (1, 567, 0, 0, 0, 2, 567, NOW(), NULL), -- Admin
    (1, 567, 0, 0, 0, 18, 567, NOW(), NULL); -- Loja

-- 7.9 Update Status Kanban (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 568, 0, 0, 0, 1, 568, NOW(), NULL), -- Super Admin
    (1, 568, 0, 0, 0, 2, 568, NOW(), NULL), -- Admin
    (1, 568, 0, 0, 0, 18, 568, NOW(), NULL); -- Loja

-- 7.10 Dashboard (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 569, 0, 0, 0, 1, 569, NOW(), NULL), -- Super Admin
    (1, 569, 0, 0, 0, 2, 569, NOW(), NULL), -- Admin
    (1, 569, 0, 0, 0, 18, 569, NOW(), NULL); -- Loja

-- 7.11 Get Chart Data (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 570, 0, 0, 0, 1, 570, NOW(), NULL), -- Super Admin
    (1, 570, 0, 0, 0, 2, 570, NOW(), NULL), -- Admin
    (1, 570, 0, 0, 0, 18, 570, NOW(), NULL); -- Loja

-- 7.12 Get Statistics (Todos os níveis)
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 571, 0, 0, 0, 1, 571, NOW(), NULL), -- Super Admin
    (1, 571, 0, 0, 0, 2, 571, NOW(), NULL), -- Admin
    (1, 571, 0, 0, 0, 18, 571, NOW(), NULL); -- Loja

-- ============================================================================
-- 8. MENU PRINCIPAL
-- ============================================================================
-- Adiciona item ao menu lateral
-- Estrutura: id, nome, icone, ordem, adms_sit_id, created, modified

INSERT INTO adms_menus (
    id,
    nome,
    icone,
    ordem,
    adms_sit_id,
    created,
    modified
) VALUES (
    26, -- ID do menu (próximo disponível)
    'Ordens de Serviço',
    'fas fa-clipboard-list',
    100, -- Ordem no menu (ajuste conforme necessário)
    1, -- Ativo
    NOW(),
    NULL
);

-- ============================================================================
-- RESUMO DO REGISTRO
-- ============================================================================
-- Total de páginas registradas: 12
--
-- ID 560: Página Principal (Listagem)
-- ID 561: Adicionar Ordem
-- ID 562: Visualizar Ordem
-- ID 563: Editar Ordem (GET)
-- ID 564: Editar Ordem (POST)
-- ID 565: Deletar Ordem
-- ID 566: Upload Imagens
-- ID 567: Kanban Board
-- ID 568: Update Status (Kanban AJAX)
-- ID 569: Dashboard Executivo
-- ID 570: Get Chart Data (Dashboard AJAX)
-- ID 571: Get Statistics (AJAX)
--
-- Permissões:
-- - Super Admin (1): Acesso total (12 páginas)
-- - Admin (2): Acesso total (12 páginas)
-- - Loja (18): Acesso total exceto Deletar (11 páginas)
--
-- Menu:
-- - 1 item criado: "Ordens de Serviço"
-- ============================================================================

-- Verificação final
SELECT 'Rotas registradas com sucesso!' AS status;
SELECT COUNT(*) AS total_paginas FROM adms_paginas WHERE id BETWEEN 560 AND 571;
SELECT COUNT(*) AS total_permissoes FROM adms_nivacs_pgs WHERE adms_pagina_id BETWEEN 560 AND 571;
