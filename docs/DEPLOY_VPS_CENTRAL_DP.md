# Deploy VPS — Central DP (WhatsApp + IA)

**Versão:** 1.0
**Data:** 28 de Março de 2026
**Projeto:** Mercury — Grupo Meia Sola

---

## 1. Arquitetura

```
┌─────────────┐     WhatsApp    ┌──────────────────────────────────┐
│ Colaborador  │◄──────────────►│  VPS (Ubuntu 22/24 + Docker)     │
│ WhatsApp     │                │  ├─ Nginx (SSL/Let's Encrypt)    │
└─────────────┘                 │  ├─ Evolution API v2.3.7 :8080   │
                                │  ├─ N8N v1.79.3 :5678            │
                                │  ├─ PostgreSQL 15 :5432           │
                                │  └─ Redis 7 :6379                │
                                └──────────────┬───────────────────┘
                                               │ HTTPS
                                               ▼
                                ┌──────────────────────────────────┐
                                │  Hospedagem Compartilhada         │
                                │  portalmercury.com.br             │
                                │  └─ Mercury (PHP 8.0 + MySQL)    │
                                └──────────────────────────────────┘
```

**Fluxo:**
1. Colaborador envia mensagem via WhatsApp
2. Evolution API (VPS) recebe e encaminha para N8N via webhook interno
3. N8N chama Mercury API (`https://portalmercury.com.br`) para fluxo conversacional
4. N8N envia para Groq (IA) para classificação
5. N8N cria ticket no Mercury via API
6. N8N responde ao colaborador via Evolution API
7. Equipe DP gerencia no Kanban do Mercury (hospedagem compartilhada)
8. Mercury envia mensagens WhatsApp chamando Evolution API na VPS via HTTPS

---

## 2. Pré-requisitos

- VPS Ubuntu 22.04 ou 24.04 LTS (mínimo 2GB RAM, 1 vCPU)
- Domínio/subdomínio apontando para o IP da VPS (ex: `dp.portalmercury.com.br`)
- Acesso SSH root/sudo
- Chave API Groq (https://console.groq.com)
- JWT token de serviço do Mercury (válido por 90 dias)

---

## 3. Deploy Rápido (Script Automatizado)

```bash
# 1. Copiar arquivos para a VPS
scp -r docker/ root@IP_DA_VPS:/opt/mercury-dp/

# 2. SSH na VPS
ssh root@IP_DA_VPS
cd /opt/mercury-dp

# 3. Configurar variáveis de produção
cp .env.prod .env.prod.local
nano .env.prod.local
# Preencher: VPS_DOMAIN, POSTGRES_PASSWORD, AUTHENTICATION_API_KEY, DATABASE_CONNECTION_URI

# 4. Renomear para .env.prod (o script usa esse nome)
mv .env.prod.local .env.prod

# 5. Executar deploy
chmod +x deploy-vps.sh
sudo ./deploy-vps.sh
```

O script automatiza: instalação Docker, firewall, SSL, containers e patches Baileys.

---

## 4. Deploy Manual (Passo a Passo)

### 4.1. Instalar Docker

```bash
sudo apt-get update && sudo apt-get upgrade -y
sudo apt-get install -y ca-certificates curl gnupg

sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### 4.2. Firewall

```bash
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP (redirect + ACME)
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 4.3. Configurar Arquivos

```bash
mkdir -p /opt/mercury-dp/nginx/conf.d
cd /opt/mercury-dp

# Copiar arquivos do repositório:
# - docker-compose.prod.yml
# - .env.prod
# - nginx/nginx.conf
# - n8n-workflow-whatsapp-dp-prod.json
# - deploy-vps.sh
```

### 4.4. Configurar .env.prod

```bash
cp .env.prod .env.prod.local
```

Valores a preencher:

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `VPS_DOMAIN` | Subdomínio da VPS | `dp.portalmercury.com.br` |
| `POSTGRES_PASSWORD` | Senha forte do PostgreSQL | `openssl rand -base64 24` |
| `AUTHENTICATION_API_KEY` | API key da Evolution API | `openssl rand -hex 32` |
| `DATABASE_CONNECTION_URI` | URI com mesma senha do PG | `postgresql://evolution:SENHA@postgres:5432/evolution?schema=public` |

### 4.5. Atualizar nginx.conf

Substituir `dp.portalmercury.com.br` pelo domínio real da VPS no arquivo `nginx/nginx.conf`.

### 4.6. Gerar SSL (Let's Encrypt)

```bash
# Subir Nginx temporário (sem SSL)
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d nginx

# Gerar certificado
docker compose -f docker-compose.prod.yml --env-file .env.prod run --rm certbot \
  certonly --webroot -w /var/www/certbot \
  -d dp.portalmercury.com.br \
  --email admin@portalmercury.com.br \
  --agree-tos --no-eff-email

# Parar e reiniciar com SSL
docker compose -f docker-compose.prod.yml --env-file .env.prod down
```

### 4.7. Subir Containers

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

### 4.8. Aplicar Patches Baileys

```bash
docker exec evolution-api sh -c "
  BAILEYS=\$(find /evolution/node_modules -path '*/baileys/lib' -type d | head -1)
  sed -i 's/Platform\.WEB/Platform.MACOS/g' \$BAILEYS/Utils/validate-connection.js
  sed -i 's/passive: true,/passive: false,/g' \$BAILEYS/Utils/validate-connection.js
  sed -i '/lidDbMigrated: false/d' \$BAILEYS/Utils/validate-connection.js
  sed -i 's/await noise\.finishInit();/noise.finishInit();/g' \$BAILEYS/Socket/socket.js
"
docker restart evolution-api
```

**ATENÇÃO:** Patches são perdidos ao recriar o container. Reaplicar após `docker compose up --build`.

---

## 5. Conectar WhatsApp

Aguardar ~30 segundos após restart da Evolution API.

```bash
# Criar instância
curl -s -X POST https://ws.portalmercury.com.br/evolution/instance/create \
  -H "apikey: SUA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"instanceName":"mercury-dp","integration":"WHATSAPP-BAILEYS","qrcode":false}'

# Gerar Pairing Code (substituir NUMERO pelo número do chip DP com DDI+DDD)
curl -s "https://ws.portalmercury.com.br/evolution/instance/connect/mercury-dp?number=55NUMERO" \
  -H "apikey: SUA_API_KEY"
```

No celular: **WhatsApp > Configurações > Dispositivos vinculados > Vincular com número de telefone** → digitar o código de 8 dígitos.

---

## 6. Configurar N8N

1. Acessar `https://dp.portalmercury.com.br/n8n/`
2. Criar conta de administrador
3. Importar workflow: `n8n-workflow-whatsapp-dp-prod.json`
4. Configurar nos nodes HTTP:
   - **JWT Token:** Gerar token de serviço no Mercury (90 dias) e substituir `YOUR_JWT_TOKEN`
   - **Groq API Key:** Substituir `YOUR_GROQ_API_KEY` no node "Groq (Llama)"
   - **Evolution API Key:** Substituir `ALTERAR_EVOLUTION_API_KEY` no node "Enviar WhatsApp"
5. Ativar o workflow

### Gerar JWT Token de Serviço

```bash
curl -s -X POST https://portalmercury.com.br/mercury/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario_api@meiasola.com.br","password":"SENHA"}'
```

---

## 7. Configurar Mercury (Hospedagem Compartilhada)

Atualizar o `.env` do Mercury na hospedagem:

```env
# Antes (local)
EVOLUTION_API_URL=http://localhost:8085
EVOLUTION_API_KEY=meia-sola-evo-2026

# Depois (produção)
EVOLUTION_API_URL=https://ws.portalmercury.com.br/evolution
EVOLUTION_API_KEY=SUA_NOVA_API_KEY
EVOLUTION_INSTANCE=mercury-dp
```

---

## 8. Verificação

| Teste | Comando/Ação | Esperado |
|-------|-------------|----------|
| Containers rodando | `docker compose -f docker-compose.prod.yml ps` | 6 serviços UP |
| Evolution API | `curl https://ws.portalmercury.com.br/evolution/instance/fetchInstances -H "apikey: KEY"` | Lista de instâncias |
| N8N | Acessar `https://ws.portalmercury.com.br/n8n/` | Página de login |
| Mercury API | `curl https://portalmercury.com.br/mercury/api/v1/personnel-requests -H "Authorization: Bearer TOKEN"` | JSON com solicitações |
| Fluxo completo | Enviar mensagem WhatsApp para o chip DP | Ticket criado no Kanban |
| Chat bidirecional | Responder pelo modal no Mercury | Colaborador recebe no WhatsApp |

---

## 9. Manutenção

### Logs

```bash
# Todos os serviços
docker compose -f docker-compose.prod.yml logs -f

# Serviço específico
docker compose -f docker-compose.prod.yml logs -f evolution-api
docker compose -f docker-compose.prod.yml logs -f n8n
```

### Backup

```bash
# PostgreSQL
docker exec evolution-postgres pg_dump -U evolution evolution > backup_evolution_$(date +%Y%m%d).sql

# Volumes
docker run --rm -v mercury-dp_evolution_instances:/data -v $(pwd):/backup alpine \
  tar czf /backup/evolution_instances_$(date +%Y%m%d).tar.gz -C /data .
```

### Atualizar Containers

```bash
cd /opt/mercury-dp
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d

# Reaplicar patches Baileys após update da Evolution API!
```

### Renovar SSL

O Certbot renova automaticamente. Para forçar:

```bash
docker compose -f docker-compose.prod.yml run --rm certbot renew --force-renewal
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
```

### Reconectar WhatsApp

Se a sessão desconectar:

```bash
curl -s "https://ws.portalmercury.com.br/evolution/instance/connect/mercury-dp?number=55NUMERO" \
  -H "apikey: SUA_API_KEY"
```

---

## 10. Troubleshooting

| Problema | Causa Provável | Solução |
|----------|---------------|---------|
| Evolution API 405 | Patches Baileys não aplicados | Reaplicar patches e reiniciar |
| N8N não recebe webhooks | Webhook URL errada no .env.prod | Verificar `WEBHOOK_GLOBAL_URL` |
| Mercury API 401 | JWT expirado | Gerar novo token e atualizar no N8N |
| Mercury API timeout | Hospedagem bloqueando IP da VPS | Verificar firewall da hospedagem |
| SSL expirado | Certbot não renovou | Forçar renovação manualmente |
| WhatsApp desconecta | Sessão expirou | Reconectar via Pairing Code |

---

**Elaborado por:** Equipe de Desenvolvimento — Grupo Meia Sola
