# 🟣 Guía de Integración: Instagram Direct & Comentarios

Esta guía documenta el procedimiento para vincular una cuenta de **Instagram Profesional** con **XpertiFlow CRM**, permitiendo gestionar mensajes directos (DMs) y respuestas a comentarios en publicaciones e historias dentro de la bandeja omnicanal.

---

## 📌 Requisitos Previos Esenciales
1. **Cuenta Profesional en Instagram**:
   - En la app de Instagram, ve a **Perfil** > **Configuración** > **Cuenta** > **Cambiar a cuenta profesional** (elige tipo *Empresa* o *Creador*).
2. **Vincular Instagram con tu Página de Facebook (Fanpage)**:
   - Abre tu Fanpage en Facebook > **Configuración** > **Cuentas vinculadas** > **Instagram** > **Conectar cuenta**.
3. **Habilitar acceso a mensajes en la App de Instagram**:
   - En tu app móvil de Instagram:
   - Ve a **Configuración y privacidad** > **Mensajes y respuestas a historias** > **Herramientas para mensajes**.
   - Activa el interruptor: **"Permitir acceso a los mensajes"** *(esto es indispensable para que la API de Meta pueda leer los DMs)*.

---

## 🛠️ Paso 1: Configurar la App en Meta Developers
1. En [developers.facebook.com](https://developers.facebook.com/), abre tu app de tipo **Empresa**.
2. En la lista de productos, busca **Instagram** (o **Instagram Graph API**) y haz clic en **Configurar**.
3. En el menú de productos, también añade el producto **Webhooks**.

---

## 🔑 Paso 2: Obtener el Instagram Account ID y Token
1. Abre [developers.facebook.com/tools/explorer](https://developers.facebook.com/tools/explorer/):
2. En **Aplicación de Meta**, selecciona tu app.
3. En **Permisos** (Permissions), añade:
   - ✅ `instagram_basic`
   - ✅ `instagram_manage_messages`
   - ✅ `instagram_manage_comments`
   - ✅ `pages_show_list`
   - ✅ `pages_read_engagement`
4. En el desplegable **Identificador de usuario o página**, selecciona tu cuenta de Instagram.
5. Copia el **Token de acceso** generado.
6. Para obtener el **Instagram Business Account ID**:
   - Ejecuta una consulta en el explorador: `GET /me/accounts?fields=instagram_business_account`
   - Te devolverá el identificador numérico de tu cuenta de Instagram (ej. `178414058291048`).

---

## 💻 Paso 3: Registrar el Canal en el CRM
1. Ve al CRM en **Canales & Conexiones** (`/app/whatsapp`).
2. Haz clic en **"+ Vincular Nuevo Canal"** > pestaña **Instagram Direct**.
3. Llena los datos:
   - **Nombre del Canal**: Ej. `Instagram Comercial Oficial`
   - **Instagram Account ID**: El ID numérico (ej. `178414058291048`).
   - **Usuario de Instagram**: `@tuempresa_oficial`
   - **Access Token**: El token obtenido en el Paso 2.
4. Haz clic en **Guardar y Vincular Canal**.
5. En la tarjeta de Instagram, haz clic en **"Webhook"** y copia:
   - **URL de Callback**: `https://tu-dominio.com/api/social/comments/webhook`
   - **Token de Verificación**: Tu *Verify Token*.

---

## 🌐 Paso 4: Configurar el Webhook en Meta
1. En Meta Developers > **Webhooks** > selecciona el objeto **Instagram**:
2. Haz clic en **Suscribirse a este objeto**:
   - **URL de devolución de llamada**: `https://tu-dominio.com/api/social/comments/webhook`
   - **Token de verificación**: Tu *Verify Token*.
3. Haz clic en **Verificar y guardar**.
4. En los campos de suscripción, activa:
   - ✅ **`messages`** *(mensajes directos entrantes)*
   - ✅ **`comments`** *(comentarios en fotos y reels)*
   - ✅ **`mentions`** *(menciones públicas en historias y posts)*

---

## ✅ Paso 5: Prueba de Recepción
1. Envía un DM a tu cuenta de Instagram desde otro usuario.
2. Abre la **Bandeja Omnicanal** (`/app/conversations`).
3. El mensaje aparecerá con el **badge magenta de Instagram**, listo para ser respondido directamente desde el CRM.
