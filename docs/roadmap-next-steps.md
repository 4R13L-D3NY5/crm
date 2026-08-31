# Roadmap de avance del CRM

Este documento define el orden recomendado para continuar el desarrollo del CRM a partir del estado actual del proyecto. El objetivo es priorizar utilidad real, estabilidad operativa y capacidad de adopcion por parte del equipo antes de seguir agregando modulos avanzados.

## Estado actual resumido

Actualmente el CRM ya cuenta con:

- autenticacion y organizaciones
- contactos
- empresas
- deals y pipeline base
- inbox conversacional
- integracion base con WhatsApp
- automatizaciones iniciales
- reportes
- auditoria
- permisos por rol
- documentacion tecnica base

La siguiente etapa ya no consiste en "crear modulos desde cero", sino en madurar el producto para uso operativo real.

---

## Principio de avance

El orden sano de evolucion es:

1. reforzar operacion diaria
2. cerrar flujo conversacional real
3. fortalecer pipeline comercial
4. automatizar procesos repetitivos
5. sumar IA util dentro del flujo
6. endurecer el sistema para produccion

---

## Tarea 1. Fortalecer Inbox operativo

### Objetivo

Volver el Inbox la herramienta principal de trabajo diario para agentes y supervisores.

### Alcance

- mejorar jerarquia visual de lista, hilo y paneles
- reforzar filtros por estado, responsable y canal
- mejorar experiencia de seleccion de conversaciones
- hacer mas visibles notas internas
- hacer mas claro el contexto de contacto y empresa
- mejorar estados vacios y errores del hilo

### Definition of Done

- un agente puede operar conversaciones sin confusion
- el hilo muestra contexto suficiente para responder
- las notas internas se distinguen claramente de mensajes
- la asignacion y cambio de estado se entienden visualmente

---

## Tarea 2. Completar detalle de Contactos

### Objetivo

Hacer que el detalle del contacto sea una ficha comercial util y no solo una vista de datos basicos.

### Alcance

- mostrar conversaciones relacionadas
- mostrar empresa asociada
- mostrar deals relacionados
- mostrar actividad reciente
- reforzar timeline y contexto comercial

### Definition of Done

- al abrir un contacto, el usuario entiende su contexto completo
- el detalle ya no depende de revisar varios modulos separados

---

## Tarea 3. Completar detalle de Empresas

### Objetivo

Convertir la vista de empresa en una vista de cuenta comercial.

### Alcance

- mostrar contactos asociados con mejor contexto
- mostrar deals asociados
- mostrar actividad reciente vinculada
- mejorar notas y resumen de la cuenta

### Definition of Done

- una empresa puede usarse como ficha de seguimiento comercial
- el equipo puede entender rapidamente el estado de una cuenta

---

## Tarea 4. Madurar Deals

### Objetivo

Volver el pipeline comercial una herramienta realmente util para seguimiento y cierre.

### Alcance

- agregar detalle de deal
- mostrar historial de cambios de etapa
- registrar mejor contexto de contacto y empresa
- permitir reflejar siguiente paso comercial
- preparar motivo de ganado o perdido

### Definition of Done

- cada deal tiene trazabilidad comercial real
- moverse por columnas refleja una accion concreta del proceso de ventas

---

## Tarea 5. Conectar WhatsApp real con Meta

### Objetivo

Pasar de integracion tecnica base a operacion real del canal.

### Alcance

- configurar cuenta real de Meta
- exponer webhook publico
- validar verificacion del webhook
- probar mensajes entrantes reales
- probar mensajes salientes reales
- revisar estados de entrega y errores

### Definition of Done

- entra un mensaje real y aparece en Inbox
- se puede responder desde el CRM
- los estados del mensaje se actualizan correctamente

---

## Tarea 6. Mejorar modulo WhatsApp

### Objetivo

Dar visibilidad operativa al canal mas importante del CRM.

### Alcance

- mostrar mejor los eventos recibidos
- mejorar trazabilidad de errores
- reforzar visualizacion de estados pending, sent, failed y received
- mejorar feedback de configuracion del canal

### Definition of Done

- el equipo puede entender si el canal esta bien configurado
- un fallo de envio o webhook es visible y rastreable

---

## Tarea 7. Expandir Automatizaciones

### Objetivo

Pasar de reglas basicas a automatizaciones que ahorren trabajo real.

### Alcance

- agregar mas triggers
- agregar mas acciones
- mejorar logs de ejecucion
- reforzar validacion de reglas
- preparar automatizaciones para Inbox y Deals

### Ejemplos esperados

- asignar automaticamente conversaciones
- cambiar estado por tiempo sin respuesta
- crear tarea cuando una oportunidad cambia de etapa
- disparar acciones ante mensajes con ciertas condiciones

### Definition of Done

- al menos varias reglas reales de operacion pueden ejecutarse sin intervencion manual

---

## Tarea 8. IA aplicada al Inbox

### Objetivo

Integrar IA donde realmente aporte productividad, sin perder control humano.

### Alcance

- sugerencia de respuesta
- resumen de conversacion
- clasificacion de lead
- propuesta de siguiente accion
- registro de runs y trazabilidad

### Regla funcional

Primero se trabaja en modo sugerencia y aprobacion humana. No se habilita automatizacion total en la etapa inicial.

### Definition of Done

- el usuario recibe una sugerencia util
- puede editarla antes de enviarla
- todo queda auditado

---

## Tarea 9. Reportes operativos y comerciales

### Objetivo

Pasar de metricas base a lectura ejecutiva y de supervision.

### Alcance

- mejorar dashboard
- agregar indicadores por agente
- agregar indicadores por canal
- agregar resultados del pipeline
- hacer mas clara la relacion entre conversaciones y conversion comercial

### Definition of Done

- el dashboard sirve para supervision diaria
- reportes permiten detectar problemas operativos y comerciales

---

## Tarea 10. Preparacion para produccion

### Objetivo

Endurecer el sistema para un entorno real de trabajo continuo.

### Alcance

- mover jobs a modo async estable
- reforzar colas y reintentos
- mejorar observabilidad
- revisar backups
- revisar sesiones y cookies
- reforzar permisos finos
- ampliar cobertura de pruebas

### Definition of Done

- el sistema puede operar con mayor estabilidad
- fallos de canal o de cola no rompen el flujo principal
- existe visibilidad suficiente para soporte y mantenimiento

---

## Orden de implementacion recomendado

### Fase A. Operacion y UX

1. Tarea 1. Fortalecer Inbox operativo
2. Tarea 2. Completar detalle de Contactos
3. Tarea 3. Completar detalle de Empresas
4. Tarea 4. Madurar Deals

### Fase B. Canal real

5. Tarea 5. Conectar WhatsApp real con Meta
6. Tarea 6. Mejorar modulo WhatsApp

### Fase C. Inteligencia operativa

7. Tarea 7. Expandir Automatizaciones
8. Tarea 8. IA aplicada al Inbox

### Fase D. Gestion y produccion

9. Tarea 9. Reportes operativos y comerciales
10. Tarea 10. Preparacion para produccion

---

## Recomendacion inmediata

El siguiente bloque de trabajo recomendado es:

1. Fortalecer Inbox operativo
2. Completar detalle de Contactos
3. Completar detalle de Empresas

Ese bloque tiene el mejor retorno inmediato porque mejora el uso diario del sistema sin depender aun de integraciones externas ni decisiones de infraestructura mas pesadas.
