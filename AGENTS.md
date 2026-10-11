# Gentle AI™ & Antigravity — Agent Skills Index

Cuando trabajes en este proyecto (**XpertiFlow CRM — Omnichannel Suite Pro**), carga los skills pertinentes ANTES de escribir código.

## Cómo usar este índice

1. Consulta la columna **Disparador** para encontrar los skills que corresponden a tu tarea.
2. Lee el archivo `SKILL.md` en la ruta especificada.
3. Sigue rigurosamente todos los patrones definidos en el skill y en [.agents/rules/gentle-ai-conventions.md](.agents/rules/gentle-ai-conventions.md).
4. Múltiples skills pueden aplicarse de manera simultánea.

---

## SDD & ODD Workflow Skills

| Skill | Disparador | Ruta |
|---|---|---|
| `sdd-init` | Inicializar contexto SDD, capacidades de prueba y registro. | [`.agents/skills/sdd-init/SKILL.md`](.agents/skills/sdd-init/SKILL.md) |
| `sdd-explore` | Explorar arquitectura, dependencias y riesgos antes de proponer cambios. | [`.agents/skills/sdd-explore/SKILL.md`](.agents/skills/sdd-explore/SKILL.md) |
| `sdd-propose` | Redactar propuestas de cambio, motivación y alcance. | [`.agents/skills/sdd-propose/SKILL.md`](.agents/skills/sdd-propose/SKILL.md) |
| `sdd-spec` | Especificar contratos, invariantes y casos límite. | [`.agents/skills/sdd-spec/SKILL.md`](.agents/skills/sdd-spec/SKILL.md) |
| `sdd-design` | Diseñar arquitectura técnica, modelos y esquemas de BD antes de codificar. | [`.agents/skills/sdd-design/SKILL.md`](.agents/skills/sdd-design/SKILL.md) |
| `sdd-tasks` | Descomponer la implementación en unidades de trabajo atómicas y testeables. | [`.agents/skills/sdd-tasks/SKILL.md`](.agents/skills/sdd-tasks/SKILL.md) |
| `sdd-apply` | Implementar tareas respetando TDD, modularidad y clean code. | [`.agents/skills/sdd-apply/SKILL.md`](.agents/skills/sdd-apply/SKILL.md) |
| `sdd-verify` | Validar implementación mediante tests automatizados y verificación Docker. | [`.agents/skills/sdd-verify/SKILL.md`](.agents/skills/sdd-verify/SKILL.md) |
| `sdd-archive` | Consolidar entregables, actualizar bitácora y cerrar la unidad de trabajo. | [`.agents/skills/sdd-archive/SKILL.md`](.agents/skills/sdd-archive/SKILL.md) |

---

## Work-Unit & Code Craft Skills

| Skill | Disparador | Ruta |
|---|---|---|
| `work-unit-commits` | Planificar commits como unidades de valor revisables (~400 líneas, tests con código). | [`.agents/skills/work-unit-commits/SKILL.md`](.agents/skills/work-unit-commits/SKILL.md) |
| `cognitive-doc-design` | Redactar documentación y especificaciones con mínima carga cognitiva. | [`.agents/skills/cognitive-doc-design/SKILL.md`](.agents/skills/cognitive-doc-design/SKILL.md) |
| `comment-writer` | Redactar comentarios de PR y notas de colaboración asíncrona. | [`.agents/skills/comment-writer/SKILL.md`](.agents/skills/comment-writer/SKILL.md) |
| `branch-pr` | Preparar ramas y PRs individuales para revisión. | [`.agents/skills/branch-pr/SKILL.md`](.agents/skills/branch-pr/SKILL.md) |
| `chained-pr` | Dividir funcionalidades extensas en PRs encadenados dentro del presupuesto cognitivo. | [`.agents/skills/chained-pr/SKILL.md`](.agents/skills/chained-pr/SKILL.md) |
| `systemic-issue-triage` | Diagnosticar errores complejos y cuellos de botella en el stack. | [`.agents/skills/systemic-issue-triage/SKILL.md`](.agents/skills/systemic-issue-triage/SKILL.md) |
| `judgment-day` | Evaluación final de riesgos y sanidad previa a entrega. | [`.agents/skills/judgment-day/SKILL.md`](.agents/skills/judgment-day/SKILL.md) |

---

## Referencias de Arquitectura del Repositorio

- **Backend**: Laravel 12, PHP 8.3, Monolito Modular (`apps/api/app/Modules/`), PostgreSQL 16 + `pgvector`, Redis 7.4, Mailpit.
- **Frontend**: Quasar Framework 2, Vue 3.5, Vite, TypeScript, Pinia, `@tanstack/vue-query` (`apps/web/`).
- **Módulo HentleAi**: Copiloto IA conversacional (`apps/api/app/Modules/HentleAi/`) con embeddings vectoriales de 768 dimensiones para soporte RAG y sugerencias en tiempo real.
- **Docker Compose**: Entorno 100% contenerizado: `crm-postgres`, `crm-redis`, `crm-mailpit`, `crm-api`, `crm-web`, `crm-nginx`.
- **Plan Maestro de Reconstrucción V2**:
  - Consulta [`docs/v2-blueprint-menu-by-menu.md`](docs/v2-blueprint-menu-by-menu.md) para la especificación detallada de cada menú, contratos de endpoints, diseño UI y pruebas unitarias/funcionales requeridas.
- **Ejecución de Pruebas**:
  ```bash
  docker compose exec -e APP_ENV=testing api php artisan test
  ```

---

## Regla de Oro de Entorno: Producción Oficial (https://crm.unitepc.pro)

- **ENTORNO DE REFERENCIA Y VERIFICACIÓN:**
  El sistema se encuentra **100% en producción en la nube** en el servidor VPS de UNITEPC:
  - **Dominio Oficial:** `https://crm.unitepc.pro`
  - **Stack Portainer:** `crm` (ID 78) en `https://200.58.81.22:9443`
  - **Nginx Proxy Manager:** Host 21 en `http://200.58.81.22:81`
- **REGLAS MANDATORIAS PARA EL AGENTE:**
  1. **NUNCA pedirle al usuario que verifique en `localhost` ni en `localhost:9010`.** Todo enlace, verificación o prueba que se solicite al usuario debe ser en el servidor oficial: `https://crm.unitepc.pro/app/...`.
  2. **Siempre desplegar a producción:** Al completar cambios o nuevas funciones, compilar, enviar a `v2-rebuild` y sincronizar/redesplegar el Stack en Portainer para que el usuario pueda interactuar con los cambios en vivo en el servidor.

