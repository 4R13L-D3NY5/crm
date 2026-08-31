# 🚀 XpertiFlow CRM — Omnichannel Suite Pro

Plataforma CRM Omnicanal Empresarial con IA (RAG), Gestión de Tickets Multicanal (WhatsApp Web QR / Cloud API, Facebook, Instagram, TikTok), Transcripción de Audio (Voice-to-Text / TTS), Pipeline Kanban y Auditoría Forense.

---

## ⚡ Guía de Instalación Rápida en una Nueva Computadora (Windows 11 / Mac / Linux)

### 🟢 Opción A: Modo Nativo (Ultra Rápido y sin Docker)

#### 1. Clonar el repositorio
```bash
git clone https://github.com/4R13L-D3NY5/xpertiflow-crm.git
cd xpertiflow-crm
```

#### 2. Configurar Backend (Laravel API)
```bash
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8010
```

#### 3. Configurar Frontend (Quasar / Vue 3 / Vite)
En otra terminal:
```bash
cd apps/web
npm install
npm run dev
```

🌐 **Acceso Web:** [http://localhost:9010](http://localhost:9010)  
🔌 **API Backend:** [http://localhost:8010/api](http://localhost:8010/api)  
📑 **Documentación OpenAPI:** [http://localhost:8010/api/docs/openapi.json](http://localhost:8010/api/docs/openapi.json)

---

### 🐳 Opción B: Con Docker Desktop (Entorno Productivo con PostgreSQL + pgvector)

```bash
docker compose up -d
cd apps/web
npm install
npm run dev
```

---

## 🧪 Ejecución de Pruebas Automatizadas (TDD)
```bash
cd apps/api
php artisan test
```
*Total de 32 tests / 177 aserciones en verde.*
