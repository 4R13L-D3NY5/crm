# 📡 XpertiFlow CRM — Guías Maestras de Integración Omnicanal

Bienvenido al centro de documentación técnica y operativa para la vinculación de canales de mensajería y redes sociales con **XpertiFlow CRM V2**.

Esta carpeta contiene manuales paso a paso diseñados para que cualquier administrador o desarrollador pueda conectar cuentas de producción o entornos de prueba sin fricción.

---

## 📚 Índice de Guías Disponibles

| Guía | Canal | Método de Conexión | Nivel de Complejidad |
| :--- | :--- | :--- | :--- |
| [01. Facebook Fanpage](./01-facebook-fanpage.md) | Facebook Messenger y Comentarios | Meta Graph API & Webhooks | Intermedio |
| [02. WhatsApp Channels](./02-whatsapp-channels.md) | WhatsApp Web (QR) y WhatsApp Cloud API | Socket Baileys / Meta Cloud API | Fácil / Intermedio |
| [03. Instagram Direct](./03-instagram-direct.md) | Instagram Direct y Respuestas a Comentarios | Instagram Graph API | Intermedio |
| [04. TikTok Business](./04-tiktok-business.md) | TikTok Mensajería y Comentarios de Leads | TikTok for Business API | Intermedio |
| [05. Despliegue en Producción](./05-production-deployment-webhooks.md) | Todos los Canales | Dominios HTTPS, Nginx y Webhooks Reales | Operativo |

---

## ⚡ Conceptos Clave de Arquitectura Omnicanal

1. **Unificación en Base de Datos**:
   - Todas las conexiones se registran en la tabla `whatsapp_accounts` (`WhatsAppAccount` model), administrando tokens, credenciales y estados (`CONNECTED`, `CONNECTING`, `DISCONNECTED`).
2. **Endpoints Centralizados de Webhooks**:
   - **WhatsApp Cloud API**: `GET /api/whatsapp/webhook` (Verificación) y `POST /api/whatsapp/webhook` (Recepción).
   - **Facebook, Instagram y TikTok**: `GET /api/social/comments/webhook` (Verificación de Challenge) y `POST /api/social/comments/webhook` (Recepción de mensajes y comentarios).
3. **Bandeja de Entrada Unificada**:
   - Sin importar el canal de origen, los mensajes se convierten automáticamente en un `Contact`, abren una `Conversation` (clasificada por canal con su color representativo) y emiten eventos de WebSocket en tiempo real hacia la bandeja `/app/conversations`.
