# 🚀 Guía de Despliegue en Producción: Dominios Reales, SSL y Webhooks

Esta guía explica cómo realizar la transición desde el entorno de desarrollo local (con túneles temporales) hacia un **servidor de producción definitivo** (VPS en Ubuntu, Debian, AWS, DigitalOcean, etc.) con dominio propio y certificado SSL (HTTPS).

---

## 🏗️ 1. Arquitectura de Producción
En producción, no se utilizan túneles como Cloudflare Tunnel temporal o Ngrok. El flujo es directo y de alta velocidad:

```
[ Cliente en Facebook / WhatsApp / Instagram / TikTok ]
                     │
                     ▼
          [ Servidor de Meta / TikTok ]
                     │  (POST HTTPS directo)
                     ▼
       https://crm.tuempresa.com:443
                     │
           [ Nginx Reverse Proxy ]
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
   /api/* (CRM API)           /* (CRM Web)
   puerto 8010                puerto 9010
```

---

## 🌐 2. Configuración de DNS y Dominio
1. Adquiere tu dominio (ej. `tuempresa.com`).
2. En tu proveedor de DNS (Cloudflare, Namecheap, GoDaddy, etc.), crea un registro **A**:
   - **Tipo**: `A`
   - **Nombre / Host**: `crm` (para crear `crm.tuempresa.com`) o `@` (para la raíz).
   - **Valor**: La dirección IP pública de tu servidor VPS (ej: `198.51.100.25`).
   - **TTL**: Automático o 300 segundos.

---

## 🔒 3. Certificado SSL con Let's Encrypt (Certbot)
En tu servidor Linux:
```bash
# Instalar Certbot
sudo apt update && sudo apt install -y certbot python3-certbot-nginx

# Generar certificado automático para tu dominio
sudo certbot certonly --standalone -d crm.tuempresa.com
```

Esto generará tus certificados seguros en:
`/etc/letsencrypt/live/crm.tuempresa.com/fullchain.pem` y `privkey.pem`.

---

## ⚙️ 4. Configuración del Archivo `.env` en Producción
En `apps/api/.env`:
```env
APP_NAME="XpertiFlow CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.tuempresa.com

# Base de datos y caché
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=crm
DB_USERNAME=crm
DB_PASSWORD=tu_password_seguro_produccion

REDIS_HOST=redis
REDIS_PORT=6379
```

En `apps/web/.env.production`:
```env
VITE_API_BASE_URL=https://crm.tuempresa.com/api
```

---

## 🔄 5. Actualización de Webhooks en las Consolas de Desarrollador

Una vez que tu servidor esté respondiendo bajo tu dominio con HTTPS, solo debes actualizar la URL en las consolas correspondientes:

### Meta Developers (WhatsApp Cloud API):
- **URL de devolución de llamada**:  
  `https://crm.tuempresa.com/api/whatsapp/webhook`
- **Token de verificación**: El *Verify Token* asignado en el CRM.

### Meta Developers (Facebook Fanpage & Instagram Direct):
- **URL de devolución de llamada**:  
  `https://crm.tuempresa.com/api/social/comments/webhook`
- **Token de verificación**: El *Verify Token* asignado en el CRM.

### TikTok for Business:
- **Callback URL**:  
  `https://crm.tuempresa.com/api/social/comments/webhook`
- **Verification Token**: El *Verify Token* asignado en el CRM.

---

## 🩺 6. Verificación de Salud en Producción
Para verificar que tus endpoints públicos de webhook están respondiendo perfectamente desde cualquier parte de internet:

```bash
# Probar endpoint de salud
curl -I https://crm.tuempresa.com/api/health

# Probar verificación del Webhook de WhatsApp
curl -i "https://crm.tuempresa.com/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge=12345&hub.verify_token=TU_TOKEN"

# Probar verificación del Webhook Social (Facebook/Instagram)
curl -i "https://crm.tuempresa.com/api/social/comments/webhook?hub_mode=subscribe&hub_challenge=12345&hub_verify_token=TU_TOKEN"
```
Ambos deben devolver `HTTP/1.1 200 OK` con el código de challenge en el cuerpo de la respuesta.
