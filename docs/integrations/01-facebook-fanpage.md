# 📘 Guía de Integración: Facebook Fanpage & Messenger

Esta guía documenta el procedimiento completo para conectar una **Página de Facebook (Fanpage)** a **XpertiFlow CRM**, permitiendo que todos los mensajes de **Messenger** y comentarios en publicaciones entren a la bandeja omnicanal en tiempo real.

---

## 📌 Requisito Fundamental de Meta
Meta **no permite** conectar perfiles personales de Facebook a herramientas externas por políticas de privacidad. La mensajería corporativa siempre opera a través de **Páginas de Facebook (Fanpages)**.

---

## 🛠️ Paso 1: Crear la Página de Facebook (Fanpage)
1. Entra a [facebook.com](https://www.facebook.com) con tu cuenta de Facebook.
2. Ve al menú superior > **Páginas** > **Crear nueva página** (o ingresa directo a [facebook.com/pages/create](https://www.facebook.com/pages/create)).
3. Ingresa el **Nombre de la página** y la **Categoría**.
4. Haz clic en **Crear página**.
5. **Obtén tu Page ID**:
   - Mira la URL en la barra de direcciones de tu navegador:  
     `https://www.facebook.com/profile.php?id=61595163417207&sk=about`  
   - El número que está después de `id=` es tu **Facebook Page ID** (ej: `61595163417207`).

---

## 👨‍💻 Paso 2: Registrarse en Meta for Developers
1. Ingresa a [developers.facebook.com](https://developers.facebook.com/).
2. Inicia sesión con tu cuenta de Facebook y haz clic en **Empezar (Get Started)** o **Mis Apps**.
3. Verifica tu cuenta con un número de teléfono móvil mediante código SMS (trámite gratuito que se hace una sola vez).
4. Confirma tu correo electrónico y selecciona el rol de **Desarrollador** o **Propietario de la empresa**.

---

## 🚀 Paso 3: Crear la Aplicación en Meta
1. En [developers.facebook.com/apps](https://developers.facebook.com/apps), haz clic en el botón verde **Crear app**.
2. **Selecciona el tipo de aplicación**:
   - Elige **Empresa** (el ícono del maletín 💼, equivalente a *Business*).
   - Haz clic en **Siguiente**.
3. **Detalles de la app**:
   - Nombre para mostrar: `CRM Conector` (o el nombre que elijas).
   - Correo electrónico de contacto: Tu email.
4. Haz clic en **Crear aplicación** e introduce tu contraseña de Facebook.

---

## 🔑 Paso 4: Conectar la Página y Generar el Token (Page Access Token)
1. En el panel principal de tu app, en la lista de **Productos disponibles**, busca **Messenger** y haz clic en **Configurar**.
2. En la sección **Generar identificadores de acceso** (Token Generation):
   - Haz clic en el botón azul **Conectar**.
   - Se abrirá una ventana emergente de Meta:
     - Selecciona **"Activa todos los Páginas actuales y futuros"** (o marca tu página recién creada).
     - Haz clic en **Continuar**.
     - Deja todos los permisos activados (`pages_messaging`, `pages_manage_metadata`, etc.).
     - Haz clic en **Guardar / Listo** y **Aceptar**.
3. Una vez cerrada la ventana, verás tu página en la lista con su botón para **Generar identificador / Generar token**.
4. Haz clic en **Generar**, acepta la advertencia y copia el token largo que empieza con `EAA...`.

> 💡 **Método alternativo para obtener el Token (Graph API Explorer)**:  
> Si se cierra la ventana, entra a [developers.facebook.com/tools/explorer](https://developers.facebook.com/tools/explorer/):  
> - En **Aplicación de Meta**: Selecciona tu app.  
> - En **Identificador de usuario o página**: Selecciona tu página de Facebook.  
> - El campo superior mostrará de inmediato tu **Page Access Token** listo para copiar.

---

## 💻 Paso 5: Registrar el Canal en XpertiFlow CRM
1. Abre el CRM en tu navegador: [http://localhost:9010/app/whatsapp](http://localhost:9010/app/whatsapp) (menú **Canales & Conexiones**).
2. Haz clic en **"+ Vincular Nuevo Canal"**.
3. Selecciona el chip azul **Facebook Fanpage**.
4. Llena los datos:
   - **Nombre del Canal**: Ej. `Facebook Soporte Principal`
   - **Facebook Page ID**: Tu ID obtenido en el Paso 1 (ej: `61595163417207` o el asignado en la app).
   - **Nombre o @Usuario**: Ej. `CRM test` o `@miempresa`
   - **Page Access Token**: Pega el token largo `EAA...`.
5. Haz clic en **Guardar y Vincular Canal**.
6. En la tarjeta que se crea, haz clic en **"Webhook"**:
   - Copia la **URL de Callback**: `.../api/social/comments/webhook`
   - Copia el **Token de Verificación (Verify Token)**: Tu token secreto generado.

---

## 🌐 Paso 6: Configurar el Webhook en Meta Developers

### A. Para Entorno Local (Desarrollo / Pruebas)
Como Meta está en la nube y tu CRM corre en local (`localhost`), Meta exige una URL pública HTTPS:
1. En tu servidor Docker corre el túnel de Cloudflare o ejecuta en una terminal:
   ```bash
   ngrok http 8085
   ```
2. Obtendrás una URL como `https://attended-replies-snapshot-customs.trycloudflare.com`.
3. Tu URL de Webhook será:  
   `https://attended-replies-snapshot-customs.trycloudflare.com/api/social/comments/webhook`

### B. Para Entorno de Producción
En un servidor VPS o Cloud con dominio propio y certificado SSL:
- URL de Webhook: `https://crm.tuempresa.com/api/social/comments/webhook`

### En Meta Developers:
1. En tu app, ve a **Messenger** > **Configuración de webhooks** (o en el menú lateral en **Webhooks**, seleccionando el objeto **Page**).
2. Haz clic en **Editar devolución de llamada** (o **Configurar Webhooks**):
   - **URL de devolución de llamada**: Pega tu URL del Webhook.
   - **Token de verificación**: Pega el *Verify Token* copiado del CRM.
3. Haz clic en **Verificar y guardar**. El CRM devolverá el `hub.challenge` automáticamente y se validará en 1 segundo.
4. En los campos de suscripción de la página, activa:
   - ✅ **`messages`** (Mensajes entrantes directos de clientes).
   - ✅ **`messaging_postbacks`** (Respuestas y botones interactivos).
   - ✅ **`feed`** (Comentarios en publicaciones públicas para crear tickets automáticos).

---

## ✅ Paso 7: Verificación en Vivo
1. Envía un mensaje desde cualquier cuenta de Facebook a tu Fanpage.
2. Abre la **Bandeja Omnicanal** ([http://localhost:9010/app/conversations](http://localhost:9010/app/conversations)).
3. Verás aparecer el ticket inmediatamente con el **badge azul de Facebook**, nombre del contacto y cuerpo del mensaje.
