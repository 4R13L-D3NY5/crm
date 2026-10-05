# 📌 Tablero Kanban de Tareas y Pendientes — XpertiFlow CRM V2

> **Protocolo de Comandos Rápidos:**
> - `=task` : Muestra el tablero Kanban actualizado con el estado de cada tarea.
> - `=new <descripción>` : Registra automáticamente una nueva tarea en la columna **Pendientes** asignándole un código identificador.
> - `=start <ID>` : Mueve una tarea a **En Progreso**.
> - `=done <ID>` : Marca una tarea como **Completada**.

---

## 📊 Matriz Kanban General

| ID | Tarea / Requerimiento | Módulo / Área | Prioridad | Estado | Próximo Paso |
|:---:|---|:---:|:---:|:---:|---|
| **TASK-001** | **Descarga y renderizado multimedia en Chat (Imágenes y Notas de Voz)** | WhatsApp / Inbox | 🔴 Alta | 📋 **PENDIENTE** | Implementar `downloadMediaMessage` en Baileys y reproductor/visor en `InboxChatThread.vue` |
| **TASK-002** | **Balanceador y enrutador inteligente de WhatsApp (Abaratar costos)** | Canales / Salida | 🟡 Media | 📋 **PENDIENTE** | Diseñar router híbrido (Baileys QR $0 vs Meta Cloud API vs Round-Robin multichip) |
| **TASK-003** | **Fase 5: Hentle-AI Copilot & Base de Conocimiento RAG (`pgvector`)** | Copilot / IA | 🟡 Media | 📋 **PENDIENTE** | Conectar `WabotKnowledgePage.vue` con API real y activar botón en el chat |
| **TASK-004** | **Fase 6: Bot Flows, Colas por Departamento y Campañas de Difusión** | Automatizaciones | 🟢 Normal | 📋 **PENDIENTE** | Flujos de chatbot numérico y difusiones con tasa escalonada anti-ban |
| **TASK-005** | **Fase 4: Productividad & Respuestas Rápidas (Atajos `/`)** | Mensajería | 🟢 Normal | ✅ **COMPLETADO** | Verificado en frontend y backend (12 tests pasando) |
| **TASK-006** | **Fase 3: Pipeline Comercial & Tablero Kanban (Purga de datos demo)** | Ventas / Deals | 🟢 Normal | ✅ **COMPLETADO** | Limpieza total de fakes y persistencia relacional en PostgreSQL |
| **TASK-007** | **Conexión WhatsApp Web QR Baileys & Inbound/Outbound bidireccional** | WhatsApp Service | 🔴 Alta | ✅ **COMPLETADO** | Conexión estable con preservación de sesión y guía documentada |
| **TASK-008** | **Fase 2: Audiencia, Contactos 360°, Empresas & Etiquetas** | CRM Core | 🟢 Normal | ✅ **COMPLETADO** | Directorio real sincronizado con chats de WhatsApp |
| **TASK-009** | **Fase 1: Autenticación, Multitenancy & Control de Acceso** | Auth / Core | 🟢 Normal | ✅ **COMPLETADO** | Sesión persistente y aislamiento por organización |

---

## 📋 Detalle de Tareas Pendientes

### 🔴 TASK-001: Descarga y visualización de Imágenes y Notas de Voz en Chat
- **Problema detectado:** En la bandeja omnicanal, los mensajes entrantes con imágenes o audios se muestran como texto plano (`[Imagen]`, `[Nota de voz / Audio]`).
- **Causa técnica:**
  1. `apps/whatsapp-service/src/index.js` detecta `content.imageMessage` o `content.audioMessage`, pero solo envía el texto descriptivo a Laravel sin descargar el buffer binario.
  2. `BaileysWebhookController.php` almacena el mensaje con `message_type: 'text'` y sin `media_url`.
  3. `InboxChatThread.vue` solo renderiza el campo `msg.body` en la burbuja sin componente de imagen (`<img>`) ni reproductor de audio (`<audio>` / waveform).
- **Plan de resolución:**
  - En Baileys: Usar `downloadMediaMessage` de `@whiskeysockets/baileys` para guardar el archivo en disco (`public/whatsapp_media/`) y enviar `media_url` y `media_type` a Laravel.
  - En Laravel: Guardar `media_type: 'image' | 'audio' | 'document'` y la URL pública accesible.
  - En Vue (`InboxChatThread.vue`): Renderizar tarjeta de imagen expandible con preview y barra de reproducción de audio con botón play/pausa.

---

### 🟡 TASK-002: Balanceador Inteligente de Mensajes WhatsApp (Optimización de Costos)
- **Objetivo:** Reducir a cero o minimizar el costo por mensaje cobrado por Meta Cloud API mediante un enrutador inteligente multicanal.
- **Estrategia y Viabilidad Técnica:**
  1. **Costo Cero con Baileys QR:** Toda sesión conectada mediante WhatsApp Web QR (Baileys) no genera costo alguno por conversación de Meta ($0.00 USD).
  2. **Regla de Enrutamiento Prioritario:**
     - Si la conversación inició por un número Baileys QR -> Responder por ese mismo número Baileys (Costo $0).
     - Si el cliente escribió por Cloud API y la ventana de servicio de 24 horas está abierta -> Enviar por Cloud API (Conversación ya pagada o gratuita dentro de la ventana de atención).
     - Si está fuera de la ventana de 24 horas -> Ofrecer al agente alternar el envío por el canal Baileys QR para no pagar plantilla de marketing/utilidad a Meta.
  3. **Rotación Multi-Chip (Anti-Ban):** Soporte para distribuir envíos masivos entre múltiples números QR de forma rotativa (Round-Robin o por carga de mensajes) para evitar bloqueos por políticas de spam.

---

### 🟡 TASK-003: Fase 5 — Hentle-AI Copilot & Base de Conocimiento RAG (`pgvector`)
- **Objetivo:** Copiloto de inteligencia artificial conversacional real conectado a PostgreSQL.
- **Alcance:**
  - Limpiar datos demo antiguos en `WabotKnowledgePage.vue` y conectar con `/api/ai/knowledge-bases`.
  - Generación de fragmentos (chunks) y embeddings semánticos de 768 dimensiones en `pgvector`.
  - Botón «Sugerir respuesta con IA» en el chat para que el asesor reciba respuestas redactadas con base en las políticas y servicios de la empresa.

---

### 🟢 TASK-004: Fase 6 — Bot Flows, Departamentos y Campañas Masivas
- **Objetivo:** Derivación automática a colas de asesores y campañas masivas con tasa regulada.
- **Alcance:**
  - Árbol de decisiones de chatbot para auto-clasificar clientes nuevos.
  - Asignación a departamentos (`Ventas`, `Soporte`, `Facturación`).
  - Módulo de campañas masivas anti-ban con pausas programadas entre envíos.

---

## 📈 Historial de Hitos Completados
- [x] **TASK-009**: Arquitectura base, multi-tenancy y control de roles.
- [x] **TASK-008**: Directorio de contactos y sincronización con PostgreSQL.
- [x] **TASK-007**: Baileys WhatsApp QR Service: reconexión automática 515, soporte LID y envío saliente verificado.
- [x] **TASK-006**: Pipeline comercial con 5 etapas, cálculo en tiempo real y purga de seeders demo.
- [x] **TASK-005**: Módulo de Respuestas Rápidas con atajos `/`, modal en vivo en chat y 12 tests automatizados.
