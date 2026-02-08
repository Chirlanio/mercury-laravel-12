-- =====================================================
-- Índices de Performance - Banco Cigam (PostgreSQL)
-- =====================================================
-- Data: 27/12/2025
-- Objetivo: Otimizar query do FindProduct
-- Impacto esperado: Redução de 36s → 0.1-0.5s (99% mais rápido)
-- =====================================================

-- Conectar ao banco CIGAM
\c RZMS

-- =====================================================
-- 1. Índice para busca por REFERENCIA
-- =====================================================
-- Usado em: WHERE p.referencia = 'xxx' OR p.referencia ILIKE 'xxx%'
-- Ganho: Busca exata O(log n) ao invés de O(n)
-- Tabela: ~1.000.000+ registros
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_dprodutos_referencia
ON msl_dprodutos_ (referencia);

-- Verificar se foi criado
SELECT
    schemaname,
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE tablename = 'msl_dprodutos_'
AND indexname = 'idx_dprodutos_referencia';

-- =====================================================
-- 2. Índice para busca por REFAUXILIAR
-- =====================================================
-- Usado em: WHERE p.refauxiliar = 'xxx'
-- Ganho: Busca exata O(log n) ao invés de O(n)
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_dprodutos_refauxiliar
ON msl_dprodutos_ (refauxiliar);

-- Verificar
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'msl_dprodutos_'
AND indexname = 'idx_dprodutos_refauxiliar';

-- =====================================================
-- 3. Índice para JOIN por CODBARRA
-- =====================================================
-- Usado em: LEFT JOIN ... ON e.cod_barra = p.codbarra
-- Ganho: Join O(n log m) ao invés de O(n*m)
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_dprodutos_codbarra
ON msl_dprodutos_ (codbarra);

-- Verificar
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'msl_dprodutos_'
AND indexname = 'idx_dprodutos_codbarra';

-- =====================================================
-- 4. Índice COMPOSTO para Estoque (CRÍTICO!)
-- =====================================================
-- Usado em: LEFT JOIN msl_festoqueatual_ e ON e.cod_barra = p.codbarra AND e.loja = :storeId
-- Ganho: Join otimizado com filtro de loja
-- Índice composto permite usar ambas colunas no JOIN
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_festoque_codbarra_loja
ON msl_festoqueatual_ (cod_barra, loja);

-- Verificar
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'msl_festoqueatual_'
AND indexname = 'idx_festoque_codbarra_loja';

-- =====================================================
-- 5. ANALYZE para atualizar estatísticas
-- =====================================================
-- PostgreSQL usa estatísticas para decidir qual índice usar
-- ANALYZE atualiza essas estatísticas
-- =====================================================

ANALYZE msl_dprodutos_;
ANALYZE msl_festoqueatual_;

-- =====================================================
-- 6. Verificar tamanho dos índices criados
-- =====================================================

SELECT
    indexname,
    pg_size_pretty(pg_relation_size(indexname::regclass)) AS index_size
FROM pg_indexes
WHERE tablename IN ('msl_dprodutos_', 'msl_festoqueatual_')
AND indexname LIKE 'idx_%'
ORDER BY indexname;

-- =====================================================
-- 7. Verificar uso dos índices (após alguns dias)
-- =====================================================
-- Execute após a aplicação estar rodando por alguns dias
-- para verificar se os índices estão sendo usados

SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan AS number_of_scans,
    idx_tup_read AS tuples_read,
    idx_tup_fetch AS tuples_fetched
FROM pg_stat_user_indexes
WHERE tablename IN ('msl_dprodutos_', 'msl_festoqueatual_')
AND indexname LIKE 'idx_%'
ORDER BY idx_scan DESC;

-- =====================================================
-- 8. EXPLAIN ANALYZE - Testar performance da query
-- =====================================================
-- Execute esta query ANTES e DEPOIS de criar os índices
-- Compare os tempos de execução

EXPLAIN ANALYZE
WITH produtos AS (
    SELECT
        p.referencia,
        p.refauxiliar,
        p.descricao,
        CASE
            WHEN p.tamanho IN ('330', '33', '335', '33.5') THEN '33'
            WHEN p.tamanho IN ('34', '345', '340', '34.5') THEN '34'
            WHEN p.tamanho IN ('35', '355', '350', '35.5') THEN '35'
            WHEN p.tamanho IN ('36', '365', '360', '36.5') THEN '36'
            WHEN p.tamanho IN ('375', '37.5', '37', '370') THEN '37'
            WHEN p.tamanho IN ('385', '38.5', '38', '380') THEN '38'
            WHEN p.tamanho IN ('39', '39.5', '395', '390') THEN '39'
            WHEN p.tamanho IN ('40', '40.5', '405', '400') THEN '40'
            WHEN p.tamanho IN ('UN', 'U', '01', '1', '00') AND UPPER(p.linha) <> 'CINTOS' THEN 'UN'
            WHEN p.tamanho IN ('PQ', 'P') THEN 'P'
            WHEN p.tamanho IN ('MD', 'M') THEN 'M'
            WHEN p.tamanho IN ('G', 'GD') THEN 'G'
            ELSE p.tamanho
        END tam,
        CASE WHEN e.saldo IS NULL THEN 0
            ELSE e.saldo::INTEGER
        END AS stock,
        CASE
            WHEN UPPER(p.linha) = 'SAPATOS' THEN 'CALÇADOS'
            ELSE UPPER(p.linha)
        END AS tipo,
        UPPER(p.marca) AS marca,
        CONCAT('https://www.portalmercury.com.br/assets/imagens/product/', CONCAT(p.referencia, '.jpg')) AS fotos
    FROM
        msl_dprodutos_ AS p
    LEFT JOIN msl_festoqueatual_ e ON e.cod_barra = p.codbarra AND e.loja = '1'
    WHERE (p.referencia = '20402' OR p.refauxiliar = '20402' OR p.referencia ILIKE '20402%')
    ORDER BY p.referencia ASC
    LIMIT 100
)
SELECT * FROM produtos;

-- =====================================================
-- RESULTADO ESPERADO:
-- =====================================================
-- ANTES dos índices:
--   - Seq Scan on msl_dprodutos_ (tempo: ~30.000ms)
--   - Hash Join (tempo: ~5.000ms)
--   - Planning Time: ~1ms
--   - Execution Time: ~35.000ms
--
-- DEPOIS dos índices:
--   - Index Scan using idx_dprodutos_referencia (tempo: ~0.1ms)
--   - Nested Loop Left Join (tempo: ~0.5ms)
--   - Planning Time: ~1ms
--   - Execution Time: ~100-500ms
--
-- GANHO: 35.000ms → 500ms = 70x MAIS RÁPIDO! 🚀
-- =====================================================

-- =====================================================
-- 9. Monitoramento de Performance (Opcional)
-- =====================================================
-- Criar uma view para monitorar queries lentas

CREATE OR REPLACE VIEW v_slow_queries AS
SELECT
    pid,
    usename,
    application_name,
    client_addr,
    query_start,
    state_change,
    state,
    EXTRACT(EPOCH FROM (now() - query_start)) AS duration_seconds,
    query
FROM pg_stat_activity
WHERE state != 'idle'
AND query NOT LIKE '%pg_stat_activity%'
ORDER BY query_start ASC;

-- Consultar queries lentas (> 5 segundos)
SELECT *
FROM v_slow_queries
WHERE duration_seconds > 5;

-- =====================================================
-- 10. Limpeza (se necessário remover índices)
-- =====================================================
-- ATENÇÃO: Só execute se precisar REMOVER os índices
-- Isso vai DEGRADAR a performance!

/*
DROP INDEX IF EXISTS idx_dprodutos_referencia;
DROP INDEX IF EXISTS idx_dprodutos_refauxiliar;
DROP INDEX IF EXISTS idx_dprodutos_codbarra;
DROP INDEX IF EXISTS idx_festoque_codbarra_loja;
*/

-- =====================================================
-- FIM DO SCRIPT
-- =====================================================
-- Para executar este script:
-- 1. Conecte-se ao servidor Cigam via psql ou pgAdmin
-- 2. Execute as seções 1-7 (criação de índices)
-- 3. Execute a seção 8 (EXPLAIN ANALYZE) para validar
-- 4. Monitore com a seção 9 após alguns dias
-- =====================================================
