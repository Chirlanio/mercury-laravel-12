-- ============================================================================
-- Registro de Permissões e Menu - Módulo Service Orders
-- Data: 2025-12-31
-- Descrição: Registra permissões e menu para as páginas já existentes (560-571)
-- ============================================================================

USE u401878354_meiaso26_bd_me;

-- ============================================================================
-- 1. CRIAR MENU PRINCIPAL
-- ============================================================================

INSERT INTO adms_menus (
    id,
    nome,
    icone,
    ordem,
    adms_sit_id,
    created,
    modified
) VALUES (
    26,
    'Ordens de Serviço',
    'fas fa-clipboard-list',
    100,
    1,
    NOW(),
    NULL
);

-- ============================================================================
-- 2. PERMISSÕES (adms_nivacs_pgs)
-- ============================================================================
-- Associa páginas aos níveis de acesso
-- Níveis: 1=Super Admin, 2=Admin, 18=Loja

-- 2.1 Página Principal (ID 560) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 560, 0, 1, 26, 1, 560, NOW(), NULL), -- Super Admin
    (1, 560, 0, 1, 26, 2, 560, NOW(), NULL), -- Admin
    (1, 560, 0, 1, 26, 18, 560, NOW(), NULL); -- Loja

-- 2.2 Adicionar (ID 561) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 561, 0, 0, 0, 1, 561, NOW(), NULL), -- Super Admin
    (1, 561, 0, 0, 0, 2, 561, NOW(), NULL), -- Admin
    (1, 561, 0, 0, 0, 18, 561, NOW(), NULL); -- Loja

-- 2.3 Visualizar (ID 562) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 562, 0, 0, 0, 1, 562, NOW(), NULL), -- Super Admin
    (1, 562, 0, 0, 0, 2, 562, NOW(), NULL), -- Admin
    (1, 562, 0, 0, 0, 18, 562, NOW(), NULL); -- Loja

-- 2.4 Editar (GET) (ID 563) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 563, 0, 0, 0, 1, 563, NOW(), NULL), -- Super Admin
    (1, 563, 0, 0, 0, 2, 563, NOW(), NULL), -- Admin
    (1, 563, 0, 0, 0, 18, 563, NOW(), NULL); -- Loja

-- 2.5 Editar (POST) (ID 564) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 564, 0, 0, 0, 1, 564, NOW(), NULL), -- Super Admin
    (1, 564, 0, 0, 0, 2, 564, NOW(), NULL), -- Admin
    (1, 564, 0, 0, 0, 18, 564, NOW(), NULL); -- Loja

-- 2.6 Deletar (ID 565) - Apenas Admin e Super Admin
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 565, 0, 0, 0, 1, 565, NOW(), NULL), -- Super Admin
    (1, 565, 0, 0, 0, 2, 565, NOW(), NULL); -- Admin

-- 2.7 Upload Imagens (ID 566) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 566, 0, 0, 0, 1, 566, NOW(), NULL), -- Super Admin
    (1, 566, 0, 0, 0, 2, 566, NOW(), NULL), -- Admin
    (1, 566, 0, 0, 0, 18, 566, NOW(), NULL); -- Loja

-- 2.8 Kanban (ID 567) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 567, 0, 0, 0, 1, 567, NOW(), NULL), -- Super Admin
    (1, 567, 0, 0, 0, 2, 567, NOW(), NULL), -- Admin
    (1, 567, 0, 0, 0, 18, 567, NOW(), NULL); -- Loja

-- 2.9 Update Status Kanban (ID 568) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 568, 0, 0, 0, 1, 568, NOW(), NULL), -- Super Admin
    (1, 568, 0, 0, 0, 2, 568, NOW(), NULL), -- Admin
    (1, 568, 0, 0, 0, 18, 568, NOW(), NULL); -- Loja

-- 2.10 Dashboard (ID 569) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 569, 0, 0, 0, 1, 569, NOW(), NULL), -- Super Admin
    (1, 569, 0, 0, 0, 2, 569, NOW(), NULL), -- Admin
    (1, 569, 0, 0, 0, 18, 569, NOW(), NULL); -- Loja

-- 2.11 Get Chart Data (ID 570) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 570, 0, 0, 0, 1, 570, NOW(), NULL), -- Super Admin
    (1, 570, 0, 0, 0, 2, 570, NOW(), NULL), -- Admin
    (1, 570, 0, 0, 0, 18, 570, NOW(), NULL); -- Loja

-- 2.12 Get Statistics (ID 571) - Todos os níveis
INSERT INTO adms_nivacs_pgs (permissao, ordem, dropdown, lib_menu, adms_menu_id, adms_niveis_acesso_id, adms_pagina_id, created, modified)
VALUES
    (1, 571, 0, 0, 0, 1, 571, NOW(), NULL), -- Super Admin
    (1, 571, 0, 0, 0, 2, 571, NOW(), NULL), -- Admin
    (1, 571, 0, 0, 0, 18, 571, NOW(), NULL); -- Loja

-- ============================================================================
-- VERIFICAÇÃO FINAL
-- ============================================================================

SELECT 'Permissões e menu registrados com sucesso!' AS status;
SELECT COUNT(*) AS total_permissoes FROM adms_nivacs_pgs WHERE adms_pagina_id BETWEEN 560 AND 571;
SELECT * FROM adms_menus WHERE id = 26;
