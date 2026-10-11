# Convenciones Gentle AI™ en XpertiFlow CRM — Omnichannel Suite Pro

Este proyecto sigue las convenciones y el harness de desarrollo de **Gentle AI™** (v3.2.1) por Gentleman Programming.
Todo agente o desarrollador que opere en este repositorio debe respetar estas reglas fundamentales:

---

## 1. Work-Unit Commits (Commits por unidad de trabajo)

- **Unidad de comportamiento entregable**: Cada commit o tarea debe representar una unidad lógica, testeable y comprensible por sí misma (`feat(...)`, `fix(...)`, `refactor(...)`).
- **No commitear por tipo de archivo**: Queda prohibido hacer commits separados de "solo modelos", "solo servicios" o "solo tests". Los tests y la documentación técnica pertenecen al mismo commit que implementa o modifica la funcionalidad.
- **Presupuesto de revisión cognitiva**: Mantener los cambios en bloques manejables (presupuesto objetivo de ~400 líneas cambiadas de autoría por unidad de entrega/slice). Si una funcionalidad es más grande, se divide en slices o encadenamiento ordenado (chained/stacked PRs).
- **Mensajes de commit convencionales**:
  - `feat(modulo): descripción orientada al resultado`
  - `fix(modulo): causa resuelta y comportamiento corregido`
  - `docs(...)`, `test(...)`, `refactor(...)`, etc.

---

## 2. Metodología ODD & SDD (Organic & Spec-Driven Development)

Para cambios sustanciales o nuevas funcionalidades, seguir el ciclo de fases estructurado:
1. **Explore**: Análisis de contexto, dependencias y riesgos sin mutar código.
2. **Propose / Spec**: Especificar el alcance exacto, contratos y casos límite.
3. **Design**: Definir arquitectura, modelo de datos y diseño técnico antes de escribir código de negocio.
4. **Tasks**: Descomponer la implementación en unidades atómicas y ejecutables.
5. **Apply**: Implementar respetando TDD y arquitectura limpia (Modular Monolith en Backend, Vue 3 + Quasar en Frontend).
6. **Verify**: Comprobación rigurosa mediante tests automatizados (`php artisan test`) y pruebas de integración antes de dar por cerrado el trabajo.
7. **Archive**: Consolidar evidencia y cerrar la unidad de trabajo.

---

## 3. Lossless Blocking Prompts (Prompts de bloqueo sin pérdida)

- Cuando se requiera decisión humana (cambios arquitectónicos, breaking changes, eliminación de datos o selección de alternativas de diseño):
  - **Presentar la totalidad de las opciones**, orden original y consecuencias sin resumir destructivamente ni omitir alternativas.
  - **No inferir ni seleccionar automáticamente** en nombre del usuario cuando se requiera consentimiento explícito.

---

## 4. Context Efficiency & Delegación Dinámica

- Proteger el contexto de trabajo principal de la saturación innecesaria.
- Si una tarea requiere investigación exhaustiva (lectura de 4+ archivos grandes) o generación masiva, delegar a subagentes acotados de investigación/escritura y sintetizar el resultado en el hilo principal.
- Mantener las respuestas claras, concisas, orientadas a la acción y respaldadas por evidencia verificable.

---

## 5. Integración con el Stack Tecnológico del CRM

- **Backend (Laravel 12 / PHP 8.3 / Modular Monolith)**:
  - Respetar la modularización en `apps/api/app/Modules/{Modulo}/` (Actions, Models, Http/Controllers, Http/Requests, Http/Resources, Queries, Events, Jobs, Services).
  - Toda mutación de esquema va en migraciones versionadas en `database/migrations/`.
  - Base de datos principal en PostgreSQL 16 con extensión vectorial `pgvector` para soporte semántico de embeddings (768 dimensiones para Gemini / 1536 para OpenAI).
  - Colas y caché administradas con Redis y base de datos relacional.
  - TDD riguroso: pruebas en `apps/api/tests/` ejecutadas mediante `docker compose exec api php artisan test`.
- **Frontend (Quasar Framework 2 / Vue 3.5 / Vite / TypeScript)**:
  - Estructura modular en `apps/web/src/` (components, pages, stores, router, services, types).
  - Reactividad con Composition API (`<script setup lang="ts">`), Pinia para gestión de estado, y `@tanstack/vue-query` para server state.
  - Tipado estricto en TypeScript sin `any` innecesarios.
- **Módulo HentleAi / Copiloto IA**:
  - Ubicado en `apps/api/app/Modules/HentleAi`.
  - Proporciona sugerencias contextuales de respuesta a tickets multicanal (WhatsApp, redes sociales, correo), resumen automático de conversaciones y búsqueda de base de conocimientos vectorizada.
- **Entorno Docker Completo**:
  - Contenedores orquestados: `crm-postgres` (pgvector), `crm-redis`, `crm-mailpit`, `crm-api`, `crm-web`, y `crm-nginx` (proxy inverso unificado).

---

## 6. Regla de Oro de Entorno: Producción Oficial (https://crm.unitepc.pro)

- **Servidor Activo:** El sistema está completamente desplegado y operando en la nube en `https://crm.unitepc.pro` (Portainer Stack `crm` en `https://200.58.81.22:9443`).
- **Comportamiento Obligatorio del Asistente:**
  1. **NUNCA pedirle al usuario verificar en `localhost` o `localhost:9010`.** Todo enlace, verificación o prueba presentada al usuario debe apuntar al servidor oficial: `https://crm.unitepc.pro/app/...`.
  2. **Siempre desplegar a producción:** Al completar cambios o nuevas funciones, compilar, commitear a `v2-rebuild` y sincronizar/redesplegar el Stack en Portainer para que el usuario pueda interactuar con los cambios en vivo en el servidor.

