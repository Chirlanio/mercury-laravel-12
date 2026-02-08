# Guia de Configuração de Ambiente - Mercury System

## 🔒 Configuração Segura de Credenciais

Este guia explica como configurar o sistema Mercury de forma segura usando variáveis de ambiente.

## ⚠️ IMPORTANTE - Segurança

**NUNCA commite arquivos com credenciais reais no Git!**

- ✅ **Commitar**: `.env.example`, `Config.php.example`
- ❌ **NUNCA Commitar**: `.env`, `Config.php`

## 📋 Passo a Passo

### 1. Configurar Variáveis de Ambiente

```bash
# 1. Copie o arquivo de exemplo
cp .env.example .env

# 2. Edite o arquivo .env com suas credenciais reais
# Use um editor de texto seguro (não exponha credenciais na tela)
```

### 2. Gerar Chave de Criptografia Segura

```bash
# No Linux/Mac:
openssl rand -hex 32

# No Windows (PowerShell):
-join ((48..57) + (65..70) | Get-Random -Count 64 | ForEach-Object {[char]$_})
```

Cole a chave gerada na variável `HASH_KEY` no arquivo `.env`.

### 3. Configurar Arquivo Config.php

```bash
# Copie o arquivo de configuração
cp core/Config.php.example core/Config.php
```

O arquivo `Config.php` irá carregar automaticamente as variáveis do `.env`.

### 4. Configurar Credenciais no .env

Edite o arquivo `.env` e preencha todas as credenciais:

```env
# Chave de Criptografia (use a chave gerada no passo 2)
HASH_KEY=sua_chave_hex_de_64_caracteres_aqui

# Banco de Dados
DB_HOST=localhost
DB_USER=seu_usuario
DB_PASS=sua_senha_secreta
DB_NAME=seu_banco
DB_PORT=3306

# Power BI API Key
POWERBI_KEY=sua_chave_powerbi_secreta

# Email SMTP
MAIL_HOST=smtp.seuservidor.com
MAIL_PORT=587
MAIL_USER=seu_usuario_email
MAIL_PASS=sua_senha_email_secreta
```

## 🔐 Boas Práticas de Segurança

### 1. Permissões de Arquivo

```bash
# Linux/Mac: Restrinja permissões do arquivo .env
chmod 600 .env

# Apenas o proprietário pode ler/escrever
```

### 2. Rotação de Credenciais

- **Imediatamente após setup inicial**: Altere todas as credenciais de exemplo
- **Regularmente**: Rotacione senhas a cada 90 dias
- **Após exposição**: Rotacione IMEDIATAMENTE se houver suspeita de exposição

### 3. Credenciais Expostas no Git

Se você acidentalmente commitou credenciais:

```bash
# 1. ROTACIONE IMEDIATAMENTE todas as credenciais expostas
# 2. Remova o arquivo do histórico do Git
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all

# 3. Force push (CUIDADO!)
git push origin --force --all
```

### 4. Ambientes Diferentes

Crie arquivos .env específicos para cada ambiente:

- `.env.development` - Desenvolvimento local
- `.env.staging` - Ambiente de testes
- `.env.production` - Produção

```php
// No Config.php, detecte o ambiente:
$envFile = '.env.' . ($_SERVER['APP_ENV'] ?? 'development');
```

## 🚀 Implantação em Produção

### Checklist de Segurança

- [ ] Arquivo `.env` criado e configurado
- [ ] Todas as credenciais rotacionadas (diferentes do exemplo)
- [ ] `HASH_KEY` gerada com 64 caracteres hex
- [ ] Permissões do `.env` restritas (600)
- [ ] `.env` NÃO está no Git
- [ ] `APP_ENV=production` configurado
- [ ] `APP_DEBUG=false` em produção
- [ ] Todas as variáveis obrigatórias preenchidas
- [ ] Credenciais testadas e funcionando

### Variáveis Obrigatórias em Produção

O sistema valida automaticamente estas variáveis em produção:

1. `HASH_KEY` - Chave de criptografia
2. `DB_HOST` - Host do banco de dados
3. `DB_USER` - Usuário do banco
4. `DB_NAME` - Nome do banco
5. `POWERBI_KEY` - Chave da API Power BI

Se alguma estiver faltando ou com valor padrão, o sistema irá **parar com erro**.

## 🔍 Troubleshooting

### Erro: "Arquivo .env não encontrado"

```bash
# Verifique se o arquivo existe
ls -la .env

# Se não existir, copie do exemplo
cp .env.example .env
```

### Erro: "Variável X não configurada"

```env
# Verifique se a variável existe no .env
cat .env | grep HASH_KEY

# Se não existir, adicione-a
echo "HASH_KEY=sua_chave_aqui" >> .env
```

### Erro: "Conexão com banco de dados falhou"

```bash
# 1. Verifique as credenciais no .env
# 2. Teste a conexão manualmente:
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME
```

## 📚 Referências

- [OWASP - Secure Configuration Management](https://cheatsheetseries.owasp.org/cheatsheets/Configuration_Management_Cheat_Sheet.html)
- [The Twelve-Factor App - Config](https://12factor.net/config)
- [PHP dotenv Best Practices](https://github.com/vlucas/phpdotenv)

## 📧 Suporte

Se encontrar problemas de configuração, entre em contato com a equipe de TI.

**🔒 Lembre-se: Segurança é responsabilidade de todos!**
