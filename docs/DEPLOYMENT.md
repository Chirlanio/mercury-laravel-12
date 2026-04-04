# Guia de Implantação — Projeto Mercury

**Versão:** 1.0
**Última Atualização:** 22 de Março de 2026

---

## Pré-requisitos do Servidor

### Software

- **PHP:** 8.0 ou superior
- **MySQL:** 8.0 com suporte a `utf8mb4_unicode_ci`
- **PostgreSQL:** Para integração com ERP Cigam (opcional)
- **Apache** com `mod_rewrite` habilitado (ou Nginx com regras equivalentes)
- **Composer:** Para gestão de dependências

### Extensões PHP obrigatórias

- `pdo_mysql`
- `pdo_pgsql` (para integração Cigam)
- `mbstring`
- `gd` ou `imagick`
- `openssl`
- `json`
- `curl`

---

## Checklist Pré-Implantação

Antes de iniciar o deploy, verifique:

- [ ] Todos os testes passando: `php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/`
- [ ] Arquivo `.env` configurado para o ambiente de destino
- [ ] Migrations pendentes identificadas em `database/migrations/`
- [ ] Backup do banco de dados realizado
- [ ] Branch `main` atualizada com as alterações aprovadas

---

## Configuração do Ambiente

### Arquivo `.env`

Copie o arquivo de exemplo e configure as variáveis:

```bash
cp .env.example .env
```

Variáveis essenciais:

```env
# Banco de dados MySQL
DB_HOST=localhost
DB_NAME=mercury
DB_USER=usuario
DB_PASS=senha

# Banco de dados PostgreSQL (Cigam)
CIGAM_HOST=servidor_cigam
CIGAM_PORT=5432
CIGAM_DB=cigam
CIGAM_USER=usuario
CIGAM_PASS=senha

# Aplicação
APP_URL=https://dominio.com.br
APP_ENV=production

# WebSocket (Chat)
WS_ENABLED=true
WS_PORT=8080
WS_IPC_PORT=8081

# JWT
JWT_SECRET=chave_secreta_segura
```

### Apache — VirtualHost

```apache
<VirtualHost *:80>
    ServerName dominio.com.br
    DocumentRoot /var/www/mercury

    <Directory /var/www/mercury>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mercury_error.log
    CustomLog ${APACHE_LOG_DIR}/mercury_access.log combined
</VirtualHost>
```

Certifique-se de que `mod_rewrite` está ativo:

```bash
a2enmod rewrite
systemctl restart apache2
```

---

## Procedimento de Implantação

### 1. Atualizar código

```bash
cd /var/www/mercury
git pull origin main
```

### 2. Instalar dependências

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Executar migrations

Aplique os scripts SQL de `database/migrations/` na ordem numérica:

```bash
# Verificar migrations pendentes
ls database/migrations/

# Executar cada migration em ordem
mysql -u usuario -p mercury < database/migrations/001_create_table.sql
mysql -u usuario -p mercury < database/migrations/002_alter_table.sql
```

**Importante:** Novas tabelas devem usar `COLLATE=utf8mb4_unicode_ci` explicitamente. O padrão do MySQL 8 (`utf8mb4_0900_ai_ci`) causa incompatibilidade com tabelas existentes em operações UNION.

### 4. Limpar caches

```bash
# Limpar cache de sessões se necessário
rm -f /tmp/sess_*
```

### 5. Reiniciar serviços

```bash
# Apache
systemctl restart apache2

# WebSocket (se habilitado)
php bin/websocket-server.php &
```

---

## Verificação Pós-Implantação

Após o deploy, verifique:

- [ ] Página inicial carrega sem erros
- [ ] Login funciona corretamente
- [ ] Módulos principais acessíveis (Vendas, Estoque, Helpdesk)
- [ ] Operações CRUD funcionam (criar, editar, excluir)
- [ ] WebSocket conecta (se habilitado) — verificar console do navegador
- [ ] Logs de erro do PHP limpos (`/var/log/apache2/mercury_error.log`)
- [ ] Integração Cigam responde (se aplicável)

---

## Procedimento de Rollback

Em caso de problemas após a implantação:

### 1. Reverter código

```bash
cd /var/www/mercury
git log --oneline -5          # Identificar o commit anterior
git checkout <commit_anterior>
```

### 2. Reverter banco de dados

Restaure o backup realizado antes do deploy:

```bash
mysql -u usuario -p mercury < backup_pre_deploy.sql
```

### 3. Reinstalar dependências

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Reiniciar serviços

```bash
systemctl restart apache2
```

### 5. Registrar incidente

Documente o problema encontrado para análise posterior, incluindo:

- Descrição do erro
- Logs relevantes
- Passos para reproduzir
- Commit que causou o problema

---

## Ambientes

| Ambiente | Branch | URL | Finalidade |
|----------|--------|-----|------------|
| Desenvolvimento | `develop` | localhost | Desenvolvimento local |
| Produção | `main` | domínio principal | Ambiente de produção |

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
