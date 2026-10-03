# 🔷 Guía de Integración: TikTok Business API

Esta guía documenta el procedimiento para vincular **TikTok for Business** con **XpertiFlow CRM**, permitiendo capturar leads, comentarios en videos y mensajes directos de campañas publicitarias.

---

## 📌 Requisitos Previos
1. Una cuenta de **TikTok for Business** activa y verificada.
2. Acceso al portal de desarrolladores de TikTok: [business-api.tiktok.com](https://business-api.tiktok.com/) o [developers.tiktok.com](https://developers.tiktok.com/).

---

## 🛠️ Paso 1: Crear la Aplicación en TikTok for Business
1. Inicia sesión en el portal de desarrolladores con tu cuenta empresarial.
2. Ve a **My Apps** > **Create App**.
3. Selecciona el tipo de app: **Commercial Application / Lead Generation**.
4. Asigna un nombre a la app (ej. `XpertiFlow TikTok Connector`).
5. En los permisos solicitados, incluye:
   - `user.info.basic`
   - `video.list`
   - `business.leads.read`
   - `business.messages`

---

## 🔑 Paso 2: Obtener Credenciales
En la pantalla de configuración de tu aplicación de TikTok:
- Copia tu **Client Key** (o App ID).
- Copia tu **Client Secret**.
- Identifica el `@usuario` de tu perfil de TikTok.

---

## 💻 Paso 3: Registrar el Canal en el CRM
1. Ve al CRM en **Canales & Conexiones** (`/app/whatsapp`).
2. Haz clic en **"+ Vincular Nuevo Canal"** > pestaña **TikTok Business**.
3. Ingresa:
   - **Nombre del Canal**: Ej. `TikTok Marca Principal`
   - **TikTok Client Key / App ID**: Tu Client Key.
   - **TikTok Client Secret**: Tu Client Secret.
   - **Usuario de TikTok**: `@tuempresa_tiktok`
4. Haz clic en **Guardar y Vincular Canal**.
5. En la tarjeta de TikTok creada, haz clic en **"Webhook"** y copia:
   - **URL de Callback**: `https://tu-dominio.com/api/social/comments/webhook`
   - **Token de Verificación**: Tu *Verify Token*.

---

## 🌐 Paso 4: Configurar el Webhook en TikTok
1. En el portal de desarrolladores de TikTok > sección **Webhooks**:
2. En **Callback URL**, pega la URL copiada del CRM:  
   `https://tu-dominio.com/api/social/comments/webhook`
3. En **Verification Token**, pega tu Verify Token.
4. Suscríbete a los eventos de interacciones de mensajería y leads de publicaciones.

---

## 🧪 Paso 5: Simulación y Pruebas
1. Puedes probar el flujo inmediatamente en el CRM haciendo clic en **"Simular Entrada"** en la tarjeta de TikTok.
2. Ingresa un usuario de TikTok simulado (ej. `@cliente_tiktok`) y un mensaje de consulta.
3. El ticket aparecerá en la **Bandeja Omnicanal** (`/app/conversations`) con el **distintivo cyan de TikTok**, listo para ser atendido.
