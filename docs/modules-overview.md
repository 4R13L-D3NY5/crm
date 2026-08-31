# Modulos del CRM

Este documento describe que hace cada modulo del CRM, cual es su objetivo, que tareas operativas se realizan dentro de el y como se conecta con los demas modulos del sistema.

## Objetivo general del producto

El CRM esta construido para unificar cuatro frentes de trabajo en un solo sistema:

- relacionamiento comercial
- seguimiento de cuentas y oportunidades
- atencion conversacional
- trazabilidad operativa

La idea central no es que cada modulo viva aislado, sino que todos colaboren entre si para cubrir un flujo completo:

1. entra una conversacion o se registra un contacto
2. el contacto se relaciona con una empresa
3. la interaccion se trabaja desde el inbox
4. si existe potencial comercial, se crea un deal
5. automatizaciones ayudan a acelerar tareas
6. reportes muestran resultados
7. auditoria deja evidencia de lo ocurrido

---

## Dashboard

### Proposito

Es la vista general del sistema. Sirve para dar contexto rapido de lo que esta ocurriendo en la organizacion activa.

### Que se hace aqui

- revisar conversaciones abiertas
- revisar conversaciones pendientes
- ver cantidad de deals activos
- ver ingresos estimados
- consultar actividad reciente
- revisar canales activos
- ver contexto de la sesion actual

### Para que sirve

- tomar pulso operativo del workspace
- detectar carga de trabajo del equipo
- identificar actividad comercial reciente
- entrar a otros modulos ya con contexto

### Que deberia mostrar idealmente

- metricas del dia, semana y mes
- variacion respecto a periodos anteriores
- alertas de conversaciones sin respuesta
- alertas de deals detenidos
- resumen por agente o por canal

### Dependencias

Consume informacion agregada de:

- Inbox
- Deals
- WhatsApp
- Reportes

---

## Contactos

### Proposito

Gestiona a las personas con las que la organizacion se relaciona. Es la base del CRM a nivel individual.

### Que se hace aqui

- crear contactos
- editar datos de contacto
- eliminar contactos
- buscar por nombre, correo o telefono
- filtrar por estado
- clasificar con etiquetas
- consultar detalle del contacto
- revisar notas e informacion asociada

### Datos que maneja

- nombre
- apellido
- correo
- telefono
- estado
- etiquetas
- notas
- fechas de creacion y actualizacion

### Para que sirve

- construir base comercial y de seguimiento
- identificar quien escribe por los canales conversacionales
- asociar personas a empresas
- vincular personas con oportunidades comerciales

### Relacion con otros modulos

- un contacto puede participar en conversaciones del Inbox
- un contacto puede estar vinculado a una empresa
- un contacto puede estar asociado a uno o varios deals
- WhatsApp intenta resolver contactos por telefono

### Flujo esperado

1. se crea manualmente o se detecta por canal
2. se completa su informacion
3. se clasifica con etiquetas o estado
4. se relaciona con empresa o deal si corresponde

---

## Empresas

### Proposito

Gestiona las cuentas, companias o instituciones relacionadas con los contactos.

### Que se hace aqui

- crear empresas
- editar informacion empresarial
- eliminar empresas
- buscar por nombre
- filtrar por estado
- revisar industria
- consultar cuantos contactos estan asociados
- ver detalle de empresa

### Datos que maneja

- nombre
- industria
- correo
- telefono
- sitio web
- estado
- notas
- contactos asociados

### Para que sirve

- trabajar cuentas y no solo personas aisladas
- preparar mejor el contexto comercial en escenarios B2B
- relacionar varias personas a una misma organizacion
- dar soporte a deals y conversaciones con marco empresarial

### Relacion con otros modulos

- una empresa puede tener muchos contactos
- una empresa puede estar asociada a uno o varios deals
- una conversacion puede mostrar referencia a empresa

### Flujo esperado

1. se registra la empresa
2. se vinculan contactos a ella
3. se centraliza informacion comercial de la cuenta
4. se usa como contexto para oportunidades y conversaciones

---

## Deals

### Proposito

Representa el pipeline comercial y las oportunidades activas del proceso de venta.

### Que se hace aqui

- crear oportunidades
- editar oportunidades
- eliminar oportunidades
- mover oportunidades entre etapas
- consultar tablero kanban
- revisar monto estimado
- ver estado comercial
- relacionar deal con contacto o empresa

### Datos que maneja

- nombre del deal
- monto
- estado
- etapa
- probabilidad
- fecha estimada de cierre
- contacto asociado
- empresa asociada
- historial de cambio de etapa

### Para que sirve

- visualizar el avance comercial
- medir pipeline activo
- ordenar el trabajo de ventas
- estimar ingresos
- identificar cuellos de botella por etapa

### Relacion con otros modulos

- usa contactos y empresas como contexto comercial
- puede originarse a partir de conversaciones del Inbox
- sus resultados impactan en Dashboard y Reportes

### Flujo esperado

1. aparece una oportunidad comercial
2. se registra como deal
3. se asocia a contacto y empresa
4. se mueve por etapas del pipeline
5. se gana, pierde o queda abierta

---

## Inbox

### Proposito

Es el centro de la operacion conversacional del CRM. Aqui se atienden los hilos de comunicacion con clientes o prospectos.

### Que se hace aqui

- listar conversaciones
- abrir una conversacion
- ver el hilo completo de mensajes
- crear conversaciones manuales
- agregar notas internas
- asignar responsable
- cambiar estado de conversacion
- revisar mensajes entrantes y salientes
- monitorear errores del canal

### Datos que maneja

- canal
- asunto o referencia
- contacto asociado
- empresa asociada
- estado
- responsable
- mensajes
- notas internas
- fecha de ultima actividad

### Para que sirve

- centralizar comunicacion del equipo
- evitar que la atencion quede fuera del CRM
- permitir colaboracion entre agentes
- convertir conversaciones en seguimiento comercial

### Estados operativos

- abierto
- pendiente
- resuelto

### Relacion con otros modulos

- recibe mensajes desde WhatsApp
- puede vincular contacto y empresa
- puede originar deals
- puede disparar automatizaciones
- sera uno de los puntos principales para IA

### Flujo esperado

1. entra o se crea una conversacion
2. se asigna a un usuario
3. se responde o se deja nota interna
4. se cambia estado segun avance
5. si hay interes comercial, se crea un deal

---

## WhatsApp

### Proposito

Conecta el CRM con WhatsApp Cloud API para trabajar mensajes reales dentro del Inbox.

### Que se hace aqui

- configurar cuenta de WhatsApp
- guardar credenciales del canal
- verificar webhook con Meta
- revisar eventos entrantes
- enviar mensajes salientes
- monitorear estados de entrega
- revisar errores del canal

### Datos que maneja

- cuenta de WhatsApp
- numero visible
- phone number id
- business account id
- verify token
- access token
- eventos webhook
- mappings de mensajes
- estados de envio y recepcion

### Para que sirve

- recibir mensajes reales dentro del CRM
- responder desde el Inbox
- mantener trazabilidad del canal
- sincronizar estados de entrega

### Regla operativa clave

Todo webhook entrante debe persistirse primero como evento crudo antes de ser procesado.

### Relacion con otros modulos

- alimenta el Inbox
- puede crear o actualizar contactos
- dispara automatizaciones
- alimenta auditoria y reportes

### Flujo esperado

1. Meta envia webhook
2. el sistema guarda el payload crudo
3. se procesa el evento
4. se identifica contacto o se crea contexto
5. se crea mensaje y conversacion si corresponde
6. el Inbox refleja la actividad

---

## Automations

### Proposito

Permite definir reglas automaticas para reducir trabajo manual y responder a eventos del sistema.

### Que se hace aqui

- crear reglas
- editar reglas
- eliminar reglas
- activar o desactivar automatizaciones
- definir trigger
- definir accion
- revisar comportamiento esperado de la regla

### Triggers actuales o esperados

- conversacion creada
- mensaje inbound recibido
- cambio de etapa de deal
- cambio de estado
- coincidencia por etiqueta o palabra clave

### Acciones actuales o esperadas

- asignar usuario
- cambiar estado
- crear tarea
- agregar etiqueta
- activar sugerencia IA
- disparar notificacion interna

### Para que sirve

- acelerar operacion
- estandarizar procesos
- evitar tareas repetitivas manuales
- mejorar tiempos de respuesta

### Relacion con otros modulos

- escucha eventos del Inbox
- puede impactar Deals
- puede apoyarse en WhatsApp
- prepara el terreno para IA

### Flujo esperado

1. ocurre un trigger
2. el sistema evalua reglas activas
3. ejecuta la accion correspondiente
4. registra la corrida
5. deja trazabilidad operativa

---

## Reportes

### Proposito

Es la capa analitica del CRM. Convierte la operacion en informacion util para toma de decisiones.

### Que se hace aqui

- revisar metricas generales
- consultar volumen de conversaciones
- seguir actividad reciente
- revisar pipeline comercial
- analizar canales y rendimiento

### Para que sirve

- evaluar rendimiento operativo
- medir impacto comercial
- detectar cuellos de botella
- comparar comportamiento entre agentes, canales o periodos

### Indicadores tipicos

- conversaciones abiertas
- conversaciones pendientes
- mensajes inbound
- deals activos
- deals ganados o perdidos
- ingresos estimados
- actividad por canal

### Relacion con otros modulos

- consume datos de Inbox
- consume datos de Deals
- toma contexto de WhatsApp
- complementa el Dashboard

### Flujo esperado

1. los modulos operativos generan actividad
2. el sistema resume esa actividad
3. reportes la presenta como lectura ejecutiva u operativa

---

## Auditoria

### Proposito

Registra eventos sensibles del sistema para tener trazabilidad y control.

### Que se hace aqui

- consultar eventos auditados
- filtrar por usuario
- filtrar por evento
- revisar fecha y contexto
- ver que entidad fue afectada

### Para que sirve

- seguridad
- control interno
- soporte tecnico
- seguimiento de errores
- revision operativa

### Eventos tipicos auditados

- login
- logout
- cambio de organizacion
- creacion de conversacion
- asignacion de conversacion
- cambio de estado
- recepcion de webhook
- reintento de envio por WhatsApp

### Preguntas que responde

- quien hizo la accion
- cuando la hizo
- sobre que entidad se hizo
- desde que contexto ocurrio

### Relacion con otros modulos

- recibe eventos de autenticacion
- recibe eventos del Inbox
- recibe eventos de WhatsApp
- ayuda a diagnosticar automatizaciones y fallos

---

## Selector de organizacion

Aunque no es un menu lateral, es una pieza critica del sistema.

### Proposito

Permitir cambiar el contexto activo entre organizaciones dentro de un CRM multiempresa.

### Que se hace aqui

- seleccionar organizacion actual
- recargar el contexto operativo del workspace

### Para que sirve

- separar datos entre empresas o unidades de negocio
- evitar mezcla de contactos, deals y conversaciones
- asegurar que los modulos muestren solo informacion del contexto activo

---

## Perfil de usuario

Tampoco es un modulo lateral, pero forma parte del uso diario.

### Proposito

Mostrar quien esta autenticado y permitir cerrar la sesion.

### Que se hace aqui

- consultar datos del usuario actual
- revisar rol actual
- cerrar sesion

### Para que sirve

- control de acceso
- trazabilidad
- seguridad operativa

---

## Como se conectan todos los modulos

### Flujo funcional resumido

1. entra un mensaje por WhatsApp o se registra manualmente una conversacion
2. el sistema identifica o crea el contacto
3. la conversacion aparece en Inbox
4. el agente atiende el caso
5. si existe oportunidad comercial, se crea un deal
6. el deal puede asociarse a una empresa
7. automatizaciones apoyan tareas repetitivas
8. Dashboard y Reportes muestran el impacto
9. Auditoria deja registro de lo ocurrido

### Lectura operacional

- Contactos y Empresas responden a quien conozco
- Inbox y WhatsApp responden como me comunico
- Deals responde que estoy intentando vender
- Automations responde que parte del trabajo puede hacer el sistema
- Dashboard y Reportes responden como va la operacion
- Auditoria responde que paso, quien lo hizo y cuando

---

## Uso sugerido por tipo de usuario

### Agente

- trabaja principalmente en Inbox
- consulta contactos y empresas
- actualiza estados
- responde conversaciones

### Comercial

- revisa contactos
- revisa empresas
- crea y mueve deals
- sigue oportunidades

### Supervisor

- revisa dashboard
- consulta reportes
- monitorea auditoria
- valida automatizaciones y carga de trabajo

### Administrador

- configura WhatsApp
- controla contexto por organizacion
- supervisa seguridad y auditoria
- mantiene la operacion general del sistema
