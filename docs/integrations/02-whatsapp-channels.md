# 📗 Guía de Integración: WhatsApp (QR Web & Meta Cloud API)

Esta guía documenta los dos métodos de conexión admitidos por **XpertiFlow CRM** para canalizar mensajes de WhatsApp:
1. **WhatsApp Web (Código QR / Socket Baileys)**: Gratuito, vinculación inmediata mediante el teléfono móvil.
2. **WhatsApp Cloud API (Meta Oficial)**: Conexión empresarial directa a los servidores de Meta para alto volumen, plantillas aprobadas y números verificados.

---

## 🟢 Modalidad 1: WhatsApp Web (Código QR)
*Ideal para atención comercial inmediata, equipos de ventas y negocios sin cuenta Meta Business verificada.*

### Procedimiento de Vinculación:
1. Ve al CRM en **Canales & Conexiones** (`/app/whatsapp`).
2. Haz clic en **"+ Vincular Nuevo Canal"**.
3. Selecciona la pestaña **WhatsApp (QR)**.
4. Ingresa:
   - **Nombre de la línea**: Ej. `Ventas Nacional Línea 1`
   - **Número visible (opcional)**: Ej. `+591 70012345`
5. Haz clic en **Guardar y Vincular Canal**.
6. De inmediato se abrirá la ventana emergente con el **Código QR**.
7. En tu teléfono móvil:
   - Abre la aplicación de WhatsApp (Normal o Business).
   - Ve a **Ajustes / Configuración** > **Dispositivos vinculados** > **Vincular un dispositivo**.
   - Apunta la cámara del teléfono hacia el código QR de la pantalla.
8. Una vez sincronizado, la tarjeta cambiará a **"Canal Conectado"**.

---

## 🟩 Modalidad 2: WhatsApp Cloud API (Meta Oficial)
*Recomendado para atención masiva, líneas corporativas fijas y campañas automatizadas.*

### Paso 1: Configurar la App en Meta Developers
1. En [developers.facebook.com](https://developers.facebook.com/), abre tu app de tipo **Empresa**.
2. En la lista de productos, busca **WhatsApp** y haz clic en **Configurar**.
3. En el menú lateral izquierdo, ve a **WhatsApp** > **Configuración de la API**:
   - Copia el **Identificador de número de teléfono (Phone Number ID)**.
   - Copia el **Identificador de la cuenta de WhatsApp Business (WABA ID)**.

### Paso 2: Generar un Token de Acceso Permanente
1. Abre [business.facebook.com](https://business.facebook.com/) > **Configuración del negocio**.
2. En el menú lateral izquierdo, ve a **Usuarios** > **Usuarios del sistema**.
3. Haz clic en **Agregar** y crea un usuario del sistema llamado `CRM-Bot` con rol **Administrador**.
4. Haz clic en **Asignar activos** y selecciona tu cuenta de **WhatsApp Business**, otorgando control total.
5. Haz clic en **Generar nuevo identificador (Token)**:
   - Caducidad: **Nunca (Permanente)**.
   - Permisos requeridos:
     - ✅ `whatsapp_business_messaging`
     - ✅ `whatsapp_business_management`
6. Copia el token permanente generado.

### Paso 3: Registrar el Canal en el CRM
1. En el CRM (`/app/whatsapp`), haz clic en **"+ Vincular Nuevo Canal"** > pestaña **WhatsApp Cloud**.
2. Ingresa:
   - **Nombre**: Ej. `WhatsApp Corporativo Oficial`
   - **Phone Number ID**: El identificador de número copiado en el Paso 1.
   - **WABA ID**: El identificador de la cuenta WABA.
   - **Permanent Access Token**: El token permanente generado en el Paso 2.
3. Haz clic en **Guardar y Vincular Canal**.
4. En la tarjeta creada, haz clic en **"Webhook"** y copia:
   - **URL de Callback**: `https://tu-dominio.com/api/whatsapp/webhook`
   - **Token de verificación**: Tu *Verify Token*.

### Paso 4: Configurar el Webhook en Meta
1. En Meta Developers > **WhatsApp** > **Configuración**:
2. En la sección **Webhook**, haz clic en **Editar**:
   - **URL de devolución de llamada**: `https://tu-dominio.com/api/whatsapp/webhook`
   - **Token de verificación**: Tu *Verify Token* copiado del CRM.
3. Haz clic en **Verificar y guardar**.
4. En **Campos de webhook**, haz clic en **Administrar** y suscríbete al campo:
   - ✅ **`messages`**

---

## 🔍 Prueba de Mensajería
1. Para probar sin enviar un mensaje real, haz clic en el botón **"Simular Entrada"** en la tarjeta de WhatsApp en el CRM.
2. Ingresa el número y nombre del remitente y haz clic en **Enviar Mensaje Simulado**.
3. El mensaje se creará y aparecerá al instante en **Bandeja Omnicanal** (`/app/conversations`).
