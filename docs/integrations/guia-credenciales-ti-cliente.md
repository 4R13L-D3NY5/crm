# 🛠️ Guía Técnica para Equipos de TI: Generación de Credenciales de Redes Sociales

Esta guía está diseñada para que el **Departamento de TI, Desarrolladores o Administradores de Sistemas** del cliente puedan aprovisionar y entregar de forma autónoma los identificadores y tokens de acceso necesarios para conectar **Facebook Fanpage, Instagram Direct y TikTok** a **XpertiFlow CRM**.

---

## 📋 Resumen de Entregables

Al finalizar esta guía, el equipo de TI debe proporcionar los siguientes valores:

| Canal | Parámetro Requerido | Formato / Ejemplo |
|---|---|---|
| **Facebook** | `Facebook Page ID` | Numérico (ej: `61595163417207`) |
| **Facebook** | `Page Access Token` | String largo (ej: `EAAG...`) |
| **Instagram** | `Instagram Business Account ID` | Numérico (ej: `178414058291048`) |
| **Instagram** | `Instagram Access Token` | String largo (ej: `EAAG...`) |
| **Instagram** | `Usuario de Instagram` | Handle público (ej: `@miempresa_bo`) |
| **TikTok** | `TikTok App ID` | Numérico (ej: `735689123456789`) |
| **TikTok** | `TikTok App Secret` | Alfanumérico hash |
| **TikTok** | `TikTok Access Token` | String largo |

---

## 1. 📘 Facebook: Page ID y Page Access Token

### 1.1 Obtener el Facebook Page ID (Enlaces Directos)

Puedes obtener el ID de tu página de inmediato usando cualquiera de estos enlaces directos:

* **Opción A (Enlace directo en Meta Business Suite - Recomendado):**  
  Abre 👉 **[https://business.facebook.com/settings/pages](https://business.facebook.com/settings/pages)**  
  *(Muestra todas tus páginas; al hacer clic sobre tu Fanpage, verás el **Identificador de la página (ID)** en letras destacadas debajo de su nombre listo para copiar).*

* **Opción B (Enlace directo en Meta Developers - 1 Clic con Graph Explorer):**  
  Abre 👉 **[https://developers.facebook.com/tools/explorer/?method=GET&path=me%2Faccounts%3Ffields%3Dname%2Cid&version=v22.0](https://developers.facebook.com/tools/explorer/?method=GET&path=me%2Faccounts%3Ffields%3Dname%2Cid&version=v22.0)**  
  *(Con tu sesión abierta, haz clic en el botón azul **Submit / Enviar** y te devolverá en pantalla el `"name"` y el `"id"` de tu página en formato JSON).*

* **Opción C (Desde Facebook tradicional):**  
  Abre tu lista de páginas en 👉 **[https://www.facebook.com/pages/?category=your_pages](https://www.facebook.com/pages/?category=your_pages)**, ingresa a tu Fanpage y ve a **Información > Transparencia de la página** (o copia el número que aparece en la URL: `facebook.com/profile.php?id=<PAGE_ID>`).


### 1.2 Crear la App en Meta Developers y Obtener el Token
1. Ingresa a **[developers.facebook.com](https://developers.facebook.com/)** con tu cuenta administradora de la página.
2. Haz clic en **Mis Apps** > **Crear app**.
3. Selecciona el tipo de caso de uso: **Otro** o **Empresa** (*Business*) y dale a **Siguiente**.
4. Asigna un nombre a la app (ej: `Conector CRM`) y coloca tu correo de contacto.
5. Dentro del panel de la App creada:
   - En la lista de productos disponibles, busca **Messenger** y haz clic en **Configurar**.
   - En la sección **Generación de identificadores de acceso** (*Token Generation*):
     - Haz clic en **Añadir o conectar página**.
     - Selecciona tu Fanpage comercial y otorga todos los permisos solicitados.
     - Haz clic en **Generar token**.
6. Copia el **Page Access Token** resultante (`EAAG...`).

> 💡 **Nota sobre vigencia del token:**  
> Si generas el token desde el **Graph API Explorer** ([developers.facebook.com/tools/explorer](https://developers.facebook.com/tools/explorer/)), asegúrate de seleccionar tu Fanpage en el menú desplegable *"Identificador de usuario o página"* y marcar los permisos:
> - `pages_show_list`
> - `pages_read_engagement`
> - `pages_manage_metadata`
> - `pages_messaging`

---

## 2. 🟣 Instagram: Account ID y Access Token

### 2.1 Prerrequisitos de la Cuenta de Instagram
Antes de generar credenciales, la cuenta de Instagram debe cumplir con estas 3 condiciones desde la app móvil:
1. **Cuenta Profesional:** En la app de Instagram > *Configuración y privacidad > Tipo de cuenta > Cambiar a cuenta profesional* (Empresa o Creador).
2. **Vinculada a la Fanpage:** En Facebook > Fanpage > *Configuración > Cuentas vinculadas > Instagram > Conectar cuenta*.
3. **Acceso a Mensajes habilitado:**  
   En la app móvil de Instagram:  
   *Configuración y privacidad > Mensajes y respuestas a historias > Herramientas para mensajes > Activar:* **«Permitir acceso a los mensajes»**. *(Obligatorio para que la API pueda leer los chats).*

### 2.2 Obtener el Instagram Business Account ID
1. Abre el **[Graph API Explorer de Meta](https://developers.facebook.com/tools/explorer/)**.
2. Selecciona tu App en el campo **Aplicación de Meta**.
3. En el campo de consulta superior escribe:
   ```http
   GET /me/accounts?fields=name,instagram_business_account{id,username}
   ```
4. Haz clic en **Submit (Enviar)**.
5. En la respuesta JSON obtendrás el identificador numérico:
   ```json
   {
     "data": [
       {
         "name": "Mi Empresa",
         "instagram_business_account": {
           "id": "178414058291048",
           "username": "miempresa_bo"
         }
       }
     ]
   }
   ```
   👉 El valor `"id"` bajo `instagram_business_account` es tu **Instagram Business Account ID**.

### 2.3 Obtener el Instagram Access Token
1. En el mismo **Graph API Explorer**, en la columna derecha **Permisos**, añade:
   - `instagram_basic`
   - `instagram_manage_messages`
   - `instagram_manage_comments`
   - `pages_show_list`
   - `pages_read_engagement`
2. En el desplegable **Identificador de usuario o página**, selecciona tu cuenta de Instagram.
3. Haz clic en **Generate Access Token** y copia el token generado.

---

## 3. 🔷 TikTok: App ID, App Secret y Access Token

### 3.1 Prerrequisito en TikTok
1. La cuenta de TikTok debe ser **Cuenta de Empresa** (en la app: *Perfil > Ajustes y privacidad > Cuenta > Cambiar a cuenta de empresa*).
2. En *Ajustes y privacidad > Privacidad > Mensajes directos*, la opción debe estar configurada en **«Todos»** (*Everyone*).

### 3.2 Registro en TikTok for Business Developers
1. Ingresa a **[business-api.tiktok.com](https://business-api.tiktok.com/)** e inicia sesión con las credenciales de la empresa.
   > ⚠️ **Importante:** TikTok exige un **correo con dominio corporativo** (ej. `@empresa.com`). Rechaza correos gratuitos como `@gmail.com`.
2. Ve al **Developer Portal** ([business-api.tiktok.com/portal](https://business-api.tiktok.com/portal)).
3. Haz clic en **Create App** (Crear aplicación):
   - **App Name:** `CRM Conector`
   - **Industry:** `IT & Software Services`
   - **Redirect URI:** `https://<TU-DOMINIO>/api/social/tiktok/callback`
4. Al crear la app, en la pestaña **App Details** obtendrás:
   - **App ID:** (Ej: `735689123456789`)
   - **App Secret:** (Haz clic en *Show* y copia la clave secreta)

### 3.3 Permisos y Generación del Access Token
1. En la configuración de la app, ve a **Permissions** y añade:
   - `business.direct_message` (Lectura y respuesta de mensajes directos)
   - `business.comment` (Lectura y respuesta de comentarios)
   - `business.lead` (Recepción de prospectos)
2. En la sección **Access Token** de la app o mediante el flujo OAuth de prueba del portal, genera y copia el **Long-lived Access Token**.

---

## 4. 📬 Formulario de Entrega para el Proveedor de CRM

Una vez generadas las credenciales, el equipo de TI puede completar esta ficha y enviarla al administrador del CRM:

```text
==================================================
FICHA DE CREDENCIALES DE REDES - XPERTIFLOW CRM
==================================================

1. FACEBOOK:
- Facebook Page ID: ____________________________________
- Page Access Token: ___________________________________

2. INSTAGRAM:
- Usuario de Instagram: @_______________________________
- Instagram Business Account ID: _______________________
- Instagram Access Token: ______________________________

3. TIKTOK:
- Usuario de TikTok: @_________________________________
- TikTok App ID: _______________________________________
- TikTok App Secret: ___________________________________
- TikTok Access Token: _________________________________

==================================================
```
