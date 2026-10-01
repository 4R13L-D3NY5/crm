# 🚀 XpertiFlow CRM — Omnichannel Suite Pro

Plataforma CRM Omnicanal Empresarial con IA (RAG), Gestión de Tickets Multicanal (WhatsApp Web QR / Cloud API, Facebook, Instagram, TikTok), Transcripción de Audio (Voice-to-Text / TTS), Pipeline Kanban, Auditoría Forense y Copiloto Inteligente Hentle-AI.

---

## ⚡ Guía de Despliegue con Docker Desktop (Recomendada y 100% Contenerizada)

Todo el ecosistema cuenta con imágenes y orquestación automática en `docker-compose.yml`, incluyendo dependencias de sistema, PostgreSQL 16 con extensión `pgvector`, Redis, Mailpit, Laravel API y Quasar Web SPA.

### 1. Iniciar los servicios
```bash
docker compose up -d
```

### 2. Puertos y Servicios Disponibles
- 🌐 **Frontend (Quasar / Vue 3):** [http://localhost:9010](http://localhost:9010)
- 🔌 **Backend API (Laravel 12):** [http://localhost:8010/api](http://localhost:8010/api)
- 🔀 **Gateway Unificado (Nginx):** [http://localhost:8085](http://localhost:8085)
- 📑 **OpenAPI / Swagger:** [http://localhost:8010/api/docs/openapi.json](http://localhost:8010/api/docs/openapi.json)
- 📧 **Mailpit Webmail:** [http://localhost:8025](http://localhost:8025)
- 🐘 **PostgreSQL 16 + pgvector:** `localhost:5433` (BD: `crm`, User: `crm`, Pass: `crm`)
- ⚡ **Redis 7.4:** `localhost:6379`

---

## 🧪 Ejecución de Pruebas Automatizadas (TDD)

Todas las pruebas se ejecutan directamente en el contenedor API:
```bash
docker compose exec -e APP_ENV=testing api php artisan test
```
*Total de 68 tests / 352 aserciones en verde (100% passing).*

---

## 🧠 Harness y Metodología Gentle AI™

El proyecto incorpora el harness completo de desarrollo determinista **Gentle AI™** (`.agents/` y `gentle-ai/`):
- Convenciones y presupuestos cognitivos en [`.agents/rules/gentle-ai-conventions.md`](.agents/rules/gentle-ai-conventions.md).
- Índice de habilidades de agente y flujo SDD/ODD en [`AGENTS.md`](AGENTS.md).
- Módulo nativo del copiloto conversacional RAG en [`apps/api/app/Modules/HentleAi`](apps/api/app/Modules/HentleAi).
