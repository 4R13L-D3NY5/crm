<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Notes\Models\Note;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\Parameters\Models\Category;
use App\Modules\QuickMessages\Models\QuickMessage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedXpertiaKnowledgeCommand extends Command
{
    protected $signature = 'crm:seed-xpertia-knowledge {--org= : ID o slug de la organización objetivo (opcional)}';
    protected $description = 'Siembra la Base de Conocimiento oficial de XpertIA+, notas Keep, respuestas rápidas, categorías y horario de guardia IA para pruebas en local';

    public function handle(): int
    {
        $this->info('=== Sincronización de Base de Conocimiento y Parámetros XpertIA+ ===');

        $orgOption = $this->option('org');

        if ($orgOption) {
            $organizations = Organization::where('slug', $orgOption)
                ->orWhere('id', $orgOption)
                ->orWhere('name', 'like', "%{$orgOption}%")
                ->get();
        } else {
            // Si no se especifica, aplicamos a todas las organizaciones de prueba existentes
            $organizations = Organization::all();
        }

        if ($organizations->isEmpty()) {
            $this->error('No se encontró ninguna organización.');
            return self::FAILURE;
        }

        $defaultUser = User::first();
        if (!$defaultUser) {
            $this->error('No existe ningún usuario registrado en el CRM.');
            return self::FAILURE;
        }

        foreach ($organizations as $org) {
            $this->info("--> Procesando organización: {$org->name} [{$org->id}]");

            // Buscar un usuario preferente de la organización
            $orgUser = $org->users()->first() ?? $defaultUser;

            $this->seedCategories($org);
            $this->seedQuickMessages($org, $orgUser);
            $this->seedKeepNotes($org, $orgUser);
            $this->seedKnowledgeBaseAndChunks($org, $orgUser);
            $this->seedBusinessHoursConfig($org);
        }

        $this->info('=== Base de Conocimiento y Parámetros XpertIA+ sembrados con éxito ===');
        return self::SUCCESS;
    }

    private function seedCategories(Organization $org): void
    {
        $this->line('   [1/5] Sincronizando Categorías de Servicios XpertIA+...');

        $categories = [
            [
                'name' => 'Consultoría Estratégica',
                'code' => 'CONS-EST',
                'slug' => 'consultoria-estrategica',
                'color' => '#10b981',
                'icon' => 'ti-chart-line',
                'sort_order' => 1,
            ],
            [
                'name' => 'Desarrollo de Software',
                'code' => 'DEV-SOFT',
                'slug' => 'desarrollo-software',
                'color' => '#3b82f6',
                'icon' => 'ti-code',
                'sort_order' => 2,
            ],
            [
                'name' => 'Ciencia de Datos & IA',
                'code' => 'DATA-IA',
                'slug' => 'ciencia-datos-ia',
                'color' => '#8b5cf6',
                'icon' => 'ti-cpu',
                'sort_order' => 3,
            ],
            [
                'name' => 'Redes y Ciberseguridad',
                'code' => 'NET-SEC',
                'slug' => 'redes-ciberseguridad',
                'color' => '#ef4444',
                'icon' => 'ti-shield-lock',
                'sort_order' => 4,
            ],
            [
                'name' => 'Marketing Digital',
                'code' => 'MKT-DIG',
                'slug' => 'marketing-digital',
                'color' => '#f59e0b',
                'icon' => 'ti-trending-up',
                'sort_order' => 5,
            ],
            [
                'name' => 'Diseño Web & Plataformas',
                'code' => 'WEB-PLAT',
                'slug' => 'diseno-web-plataformas',
                'color' => '#06b6d4',
                'icon' => 'ti-device-desktop',
                'sort_order' => 6,
            ],
            [
                'name' => 'Soporte Técnico & Guardia',
                'code' => 'SOP-TEC',
                'slug' => 'soporte-tecnico-guardia',
                'color' => '#14b8a6',
                'icon' => 'ti-headset',
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $catData) {
            Category::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'slug' => $catData['slug'],
                ],
                [
                    'name' => $catData['name'],
                    'code' => $catData['code'],
                    'color' => $catData['color'],
                    'icon' => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_selectable' => true,
                ]
            );
        }
    }

    private function seedQuickMessages(Organization $org, User $user): void
    {
        $this->line('   [2/5] Sincronizando Respuestas Rápidas (Quick Messages)...');

        $messages = [
            [
                'shortcut' => '/hola',
                'message' => "¡Hola! 👋 Te damos la bienvenida a XpertIA+ (Tu Socio Tecnológico). Ofrecemos consultoría estratégica, desarrollo de software a medida, inteligencia artificial, ciberseguridad, marketing digital y plataformas académicas. ¿En qué podemos asesorarte hoy?",
            ],
            [
                'shortcut' => '/servicios',
                'message' => "En XpertIA+ trabajamos bajo 6 frentes especializados con un solo interlocutor:\n1️⃣ Consultoría Estratégica (diagnóstico, roadmap y dirección)\n2️⃣ Desarrollo de Software (Web, Móvil en Flutter/React, ERP/CRM)\n3️⃣ Ciencia de Datos & IA (Modelos a medida, Big Data, RPA)\n4️⃣ Redes y Ciberseguridad (Infraestructura, Cloud AWS/Azure, Pentesting)\n5️⃣ Marketing Digital (SEO, Google Ads, Redes, Atribución GA4)\n6️⃣ Diseño Web & Plataformas Académicas (Moodle y Revistas OJS)\n¿Te gustaría profundizar en alguno de ellos?",
            ],
            [
                'shortcut' => '/consultoria',
                'message' => "📊 *Consultoría Estratégica XpertIA+:*\nTe ayudamos a definir en qué tecnología invertir y en qué orden. Evaluamos tus sistemas actuales, identificamos cuellos de botella y diseñamos un plan por etapas con cronograma e inversión clara. Además, asesoramos tesis y proyectos de grado y posgrado.",
            ],
            [
                'shortcut' => '/desarrollo',
                'message' => "💻 *Desarrollo de Software a Medida:*\nConstruimos aplicaciones web, móviles (iOS/Android en Flutter y React Native) y sistemas empresariales (ERP/CRM) rápidos y seguros. También modernizamos sistemas antiguos sin frenar tu operación ni perder datos. Todo con arquitecturas escalables (Docker/Kubernetes/APIs).",
            ],
            [
                'shortcut' => '/ia',
                'message' => "🤖 *Ciencia de Datos & Inteligencia Artificial:*\nDesarrollamos soluciones prácticas de IA: asistentes conversacionales 24/7 entrenados con la data de tu negocio, modelos predictivos, Big Data con Power BI y automatización de tareas administrativas repetitivas (RPA con Python y Power Automate).",
            ],
            [
                'shortcut' => '/seguridad',
                'message' => "🛡️ *Redes y Ciberseguridad:*\nProtegemos tus sistemas e información de ataques y caídas. Realizamos cableado estructurado, redes WiFi de alta densidad, servidores en la nube (AWS/Azure), pruebas de intrusión (Pentesting), firewalls y planes de respuesta a incidentes.",
            ],
            [
                'shortcut' => '/academico',
                'message' => "🎓 *Plataformas Académicas & Revistas Científicas:*\n• Aulas Virtuales con Moodle: instalación, diseño a medida, certificados automáticos y reportes.\n• Revistas Científicas con OJS (Open Journal Systems): flujo editorial de revisión por pares, asignación de DOI y asesoría para indexación en Scopus, SciELO y Latindex.",
            ],
            [
                'shortcut' => '/faq',
                'message' => "❓ *Preguntas Frecuentes XpertIA+:*\n• ¿Tiempos?: Trabajamos por sprints quincenales para que veas avances reales cada 2 semanas.\n• ¿Tamaño de empresa?: Atendemos desde startups hasta corporaciones e instituciones educativas.\n• ¿Primera consulta?: Es 100% gratuita y sin compromiso para entender tu necesidad.\n• ¿Soporte?: Brindamos acompañamiento y soporte técnico continuo post-lanzamiento.",
            ],
            [
                'shortcut' => '/contacto',
                'message' => "📞 *Canales Oficiales de XpertIA+:*\n• WhatsApp / Teléfono: +591 78311416 (wa.me/59178311416)\n• Correo Electrónico: contacto@xpertia.com\n• Portal Web: https://xpertiaplus.claure.pro/\n• Horario de atención: Lunes a viernes de 09:00 a 18:00 (Guardia IA fuera de horario).",
            ],
        ];

        foreach ($messages as $msg) {
            QuickMessage::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'shortcut' => $msg['shortcut'],
                ],
                [
                    'user_id' => $user->id,
                    'message' => $msg['message'],
                    'is_general' => true,
                ]
            );
        }
    }

    private function seedKeepNotes(Organization $org, User $user): void
    {
        $this->line('   [3/5] Sincronizando Notas Keep de Micro-conocimiento...');

        $notes = [
            [
                'title' => 'XpertIA+ — Canales de Contacto y Guardia',
                'shortcut' => 'xpertia-contacto',
                'color' => '#065f46',
                'content' => "WhatsApp Oficial: +591 78311416 (wa.me/59178311416)\nCorreo: contacto@xpertia.com\nWeb: https://xpertiaplus.claure.pro/\nHorario de Atención: Lunes a viernes de 09:00 a 18:00.\nFuera de horario: Guardia Copiloto IA 24/7 con escalación prioritaria al teléfono corporativo.",
                'is_pinned' => true,
            ],
            [
                'title' => 'XpertIA+ — Stack Tecnológico Oficial',
                'shortcut' => 'xpertia-stack',
                'color' => '#1e3a8a',
                'content' => "Frontend: React, Vue.js, Angular, Next.js, Quasar, Tailwind CSS.\nBackend: PHP (Laravel 12), Node.js, Python, Java, .NET Core.\nMóvil: Flutter, React Native, Swift, Kotlin.\nBases de Datos: PostgreSQL (con pgvector para RAG), MySQL, Redis, MongoDB.\nInfraestructura: Docker, Kubernetes, Apache Kafka, AWS, Azure, GitHub Actions, Terraform.",
                'is_pinned' => true,
            ],
            [
                'title' => 'XpertIA+ — Metodología de 5 Etapas',
                'shortcut' => 'xpertia-metodologia',
                'color' => '#581c87',
                'content' => "01 Descubrimiento: Diagnóstico inicial sin costo.\n02 Planificación: Cronograma con fechas concretas y presupuesto cerrado.\n03 Desarrollo por Sprints: Entregas cortas quincenales para ver avances reales.\n04 Calidad & Seguridad: Testing automatizado, auditoría de código y pentesting.\n05 Lanzamiento & Soporte: Despliegue asistido, capacitación y póliza de soporte continuo.",
                'is_pinned' => false,
            ],
            [
                'title' => 'XpertIA+ — Plataformas Académicas (Moodle & OJS)',
                'shortcut' => 'xpertia-academico',
                'color' => '#0e7490',
                'content' => "• Aulas Virtuales Moodle: Implementación completa, certificados automáticos, trazabilidad de alumnos y alto tráfico concurrente.\n• Revistas Científicas OJS (Open Journal Systems): Flujo editorial completo con revisión por pares a doble ciego, asignación de DOI con Crossref y preparación para indexación en Scopus, SciELO y Latindex.",
                'is_pinned' => false,
            ],
            [
                'title' => 'XpertIA+ — Políticas de Soporte y Garantía',
                'shortcut' => 'xpertia-soporte',
                'color' => '#78350f',
                'content' => "• Garantía técnica incluida en todos los desarrollos.\n• Pólizas de mantenimiento y soporte post-lanzamiento para actualizaciones y resolución de dudas.\n• Primera consultoría 100% gratuita y sin compromiso de contratación.",
                'is_pinned' => false,
            ],
            [
                'title' => 'XpertIA+ — Soluciones de IA y Automatización RPA',
                'shortcut' => 'xpertia-ia',
                'color' => '#831843',
                'content' => "• Asistente Copiloto 24/7 conectado a la base de conocimiento vectorial de la empresa.\n• Modelos predictivos entrenados con los datos del cliente.\n• Automatización Robótica de Procesos (RPA) con Python, Power Automate y UiPath para eliminar tareas administrativas repetitivas.",
                'is_pinned' => false,
            ],
        ];

        foreach ($notes as $noteData) {
            Note::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'shortcut' => $noteData['shortcut'],
                ],
                [
                    'user_id' => $user->id,
                    'title' => $noteData['title'],
                    'content' => $noteData['content'],
                    'color' => $noteData['color'],
                    'is_pinned' => $noteData['is_pinned'],
                    'is_archived' => false,
                    'visibility' => 'all',
                ]
            );
        }
    }

    private function seedKnowledgeBaseAndChunks(Organization $org, User $user): void
    {
        $this->line('   [4/5] Sincronizando Base de Conocimiento RAG & Chunks Vectoriales...');

        $base = AiKnowledgeBase::updateOrCreate(
            [
                'organization_id' => $org->id,
                'name' => 'XpertIA+ — Base Corporativa & Servicios',
            ],
            [
                'user_id' => $user->id,
                'description' => 'Base de conocimiento oficial extraída de xpertiaplus.claure.pro: Servicios, Metodología, Stack, FAQs y Canales de Contacto.',
                'provider' => 'bluesminds',
                'embedding_model' => 'google/diffusiongemma-26b-a4b-it',
                'is_active' => true,
            ]
        );

        $chunks = [
            [
                'title' => 'XpertIA+ — Identidad Corporativa y Canales Oficiales',
                'content' => "XpertIA+ es tu socio tecnológico. Slogan: 'Un solo equipo. Todo lo que tu negocio necesita.' Ofrecemos un equipo multidisciplinario integral para atender estrategia, software, inteligencia artificial, ciberseguridad, marketing y educación sin lidiar con múltiples proveedores aislados. Canales oficiales: WhatsApp directo +591 78311416 (wa.me/59178311416), correo contacto@xpertia.com, web https://xpertiaplus.claure.pro. Horario de atención: Lunes a viernes de 09:00 a 18:00 (Hora de Bolivia / GMT-4). Fuera de horario atiende el Copiloto IA con derivación a guardia técnica.",
                'metadata' => ['section' => 'identity', 'tags' => ['contacto', 'horarios', 'whatsapp', 'soporte']],
            ],
            [
                'title' => 'Servicio: Consultoría Estratégica (Visión & Futuro)',
                'content' => "Ayudamos a las empresas a decidir en qué tecnología invertir y por qué. Revisamos tus sistemas actuales e infraestructura para detectar cuellos de botella y vulnerabilidades con informe ejecutivo. Elaboramos un plan de trabajo por etapas con cronograma, presupuesto y ROI claro. Gestionamos la dirección del proyecto con Scrum, Kanban y Jira. También brindamos asesoría técnica para tesis y proyectos de grado, maestría y doctorado (metodología, prototipo, análisis de datos y preparación de defensa).",
                'metadata' => ['service' => 'consultoria', 'tags' => ['estrategia', 'tesis', 'gestion', 'scrum']],
            ],
            [
                'title' => 'Servicio: Desarrollo de Software a Medida (Hecho a tu Medida)',
                'content' => "Desarrollo de aplicaciones web, móviles y sistemas a la medida de tu negocio, rápidos, seguros y fáciles de mantener. Desarrollo de sistemas web ERP, CRM, inventarios y facturación. Apps móviles para iOS y Android en Flutter, React Native, Swift y Kotlin publicadas en App Store y Google Play. Modernización de sistemas legacy antiguos sin cortes operativos ni pérdida de datos. Arquitecturas escalables de microservicios con Docker, Kubernetes y Kafka. Automatización CI/CD con GitHub Actions y GitLab CI.",
                'metadata' => ['service' => 'desarrollo', 'tags' => ['software', 'apps', 'flutter', 'laravel', 'react']],
            ],
            [
                'title' => 'Servicio: Ciencia de Datos & Inteligencia Artificial (Inteligencia Aplicada)',
                'content' => "Convertimos datos en decisiones automatizadas y menos trabajo manual. Implementamos modelos de IA a medida entrenados con los datos del cliente para predicción, procesamiento de lenguaje natural y visión artificial. Big Data y Business Intelligence con Apache Spark, Google BigQuery y Power BI. Automatización Robótica de Procesos (RPA) con Python, Power Automate y UiPath para tareas administrativas repetitivas y lectura de documentos. Capacitaciones técnicas presenciales y virtuales.",
                'metadata' => ['service' => 'ia', 'tags' => ['ia', 'datos', 'rpa', 'powerbi', 'python']],
            ],
            [
                'title' => 'Servicio: Redes y Ciberseguridad (Protección Total)',
                'content' => "Protegemos tu empresa contra ciberataques, fugas de datos y caídas de red. Cableado estructurado, VLANs y WiFi empresarial de alta densidad con balanceo de carga. Infraestructura en la nube con AWS y Azure, optimizada y con respaldos automáticos. Auditorías de seguridad y Pentesting (ataques simulados con informes de remediación). Seguridad perimetral con Firewalls, autenticación de doble factor (2FA) y cifrado de datos. Respuesta a incidentes informáticos y recuperación forense digital (DFIR).",
                'metadata' => ['service' => 'ciberseguridad', 'tags' => ['redes', 'seguridad', 'pentesting', 'cloud', 'aws']],
            ],
            [
                'title' => 'Servicio: Marketing Digital (Crecimiento & Alcance)',
                'content' => "Hacemos que clientes calificados encuentren tu marca y compren. Posicionamiento en Google mediante SEO técnico y campañas optimizadas en Google Ads. Creación de contenidos estratégicos, blogs, guiones y newsletters. Manejo profesional de redes sociales con criterios de conversión y gestión de comunidad. Analítica web y medición de conversiones con Google Analytics 4 (GA4) y Google Tag Manager para saber qué canal trae cada venta.",
                'metadata' => ['service' => 'marketing', 'tags' => ['seo', 'google_ads', 'redes', 'ga4']],
            ],
            [
                'title' => 'Servicio: Diseño Web & Plataformas Académicas (Experiencias que Convierten)',
                'content' => "Diseño UX/UI intuitivo y accesible centrado en convertir visitas en clientes. Sitios web dinámicos autogestionables en WordPress y Strapi Headless. Implementación de Aulas Virtuales completas con Moodle LMS (cursos, matriculación, exámenes, certificados automáticos y reportes). Implementación de portales para Revistas Científicas con Open Journal Systems (OJS): flujo editorial de revisión por pares a doble ciego, asignación y registro de DOI con Crossref y asesoría para indexación en Scopus, SciELO y Latindex.",
                'metadata' => ['service' => 'diseno_academico', 'tags' => ['moodle', 'ojs', 'revistas', 'wordpress', 'doi']],
            ],
            [
                'title' => 'Metodología de Trabajo en 5 Etapas (Proceso XpertIA+)',
                'content' => "El proceso de trabajo de XpertIA+ se estructura en 5 fases secuenciales: 01 Descubrimiento (reunión inicial sin costo para entender negocio y necesidades). 02 Planificación (cronograma claro con fechas concretas y presupuesto cerrado). 03 Desarrollo por Sprints (entregas quincenales para que veas avances tangibles en entornos de prueba). 04 Calidad y Seguridad (testing automatizado, revisión de seguridad y rendimiento). 05 Lanzamiento y Soporte (puesta en producción asistida, capacitación y póliza de soporte continuo).",
                'metadata' => ['section' => 'methodology', 'tags' => ['metodologia', 'proceso', 'sprints', 'garantia']],
            ],
            [
                'title' => 'Preguntas Frecuentes: Tiempos, Costos y Tamaños de Empresa',
                'content' => "¿Cuánto tiempo toma un proyecto? Depende del alcance; en Descubrimiento te damos un cronograma claro y entregamos avances quincenales. ¿Trabajan con empresas de cualquier tamaño? Sí, desde startups y proyectos puntuales hasta sistemas ERP/CRM para corporaciones y universidades. ¿Cómo cotizan? Entendemos el requerimiento en Descubrimiento y presentamos una propuesta con precio cerrado sin costos ocultos. ¿La primera consultoría tiene costo? No, es una conversación estratégica sin costo ni compromiso.",
                'metadata' => ['section' => 'faq', 'tags' => ['faq', 'cotizacion', 'costos', 'tiempos']],
            ],
            [
                'title' => 'Preguntas Frecuentes: Soporte Post-Lanzamiento y Garantía',
                'content' => "¿Qué pasa después de que el proyecto se lanza a producción? No desaparecemos. Ofrecemos esquemas de acompañamiento y soporte técnico continuo post-lanzamiento para resolver dudas, aplicar mejoras, realizar respaldos periódicos y asegurar el correcto funcionamiento de toda la plataforma.",
                'metadata' => ['section' => 'faq', 'tags' => ['soporte', 'garantia', 'postventa']],
            ],
            [
                'title' => 'Equipo Técnico Multidisciplinario de XpertIA+',
                'content' => "El equipo técnico de XpertIA+ está conformado por especialistas con roles claros: Estrategia y Consultoría de Negocio, Dirección Técnica y Arquitectura de Sistemas/Cloud, Calidad y Desarrollo (QA), IA y Desarrollo Senior, Ciberseguridad e Infraestructura de Redes, y Desarrolladores Frontend y Backend experimentados.",
                'metadata' => ['section' => 'team', 'tags' => ['equipo', 'roles', 'senior']],
            ],
        ];

        foreach ($chunks as $chunkData) {
            AiKnowledgeChunk::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'knowledge_base_id' => $base->id,
                    'title' => $chunkData['title'],
                ],
                [
                    'user_id' => $user->id,
                    'content' => $chunkData['content'],
                    'metadata' => $chunkData['metadata'],
                ]
            );
        }
    }

    private function seedBusinessHoursConfig(Organization $org): void
    {
        $this->line('   [5/5] Sincronizando Horario de Atención & Guardia IA Nocturna...');

        $autoReplyMsg = '¡Hola! Gracias por comunicarte con XpertIA+ (Tu Socio Tecnológico). En este momento nos encontramos fuera de nuestro horario de oficina (Lunes a Viernes de 09:00 a 18:00). Nuestro Copiloto IA está activo para asesorarte sobre nuestros servicios o transferirte a la guardia técnica si es urgente.';
        $aiFallbackMsg = 'No cuento con esa información específica en este momento, pero he registrado tu solicitud y la derivé a nuestra guardia técnica (+591 78311416). Un especialista de XpertIA+ se comunicará contigo a la brevedad.';

        // Horario oficial de XpertIA+:
        // Lunes a Viernes: 09:00 a 18:00
        // Sábado y Domingo: Cerrado (atendido por guardia IA)
        $scheduleDays = [
            'monday' => [
                'enabled' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'slots' => [
                    ['name' => 'Oficina', 'start_time' => '09:00', 'end_time' => '18:00', 'enabled' => true],
                ],
            ],
            'tuesday' => [
                'enabled' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'slots' => [
                    ['name' => 'Oficina', 'start_time' => '09:00', 'end_time' => '18:00', 'enabled' => true],
                ],
            ],
            'wednesday' => [
                'enabled' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'slots' => [
                    ['name' => 'Oficina', 'start_time' => '09:00', 'end_time' => '18:00', 'enabled' => true],
                ],
            ],
            'thursday' => [
                'enabled' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'slots' => [
                    ['name' => 'Oficina', 'start_time' => '09:00', 'end_time' => '18:00', 'enabled' => true],
                ],
            ],
            'friday' => [
                'enabled' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'slots' => [
                    ['name' => 'Oficina', 'start_time' => '09:00', 'end_time' => '18:00', 'enabled' => true],
                ],
            ],
            'saturday' => [
                'enabled' => false,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'slots' => [],
            ],
            'sunday' => [
                'enabled' => false,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'slots' => [],
            ],
        ];

        BusinessHoursConfig::updateOrCreate(
            [
                'organization_id' => $org->id,
                'is_default' => true,
            ],
            [
                'name' => 'Horario Corporativo XpertIA+ & Guardia IA',
                'scope_type' => 'general',
                'is_active' => true,
                'validity_type' => 'immediate',
                'timezone' => 'America/La_Paz',
                'schedule_days' => $scheduleDays,
                'auto_reply_enabled' => true,
                'auto_reply_message' => $autoReplyMsg,
                'auto_reply_mode' => 'always',
                'auto_reply_schedule' => 'outside_business_hours',
                'auto_reply_cooldown_minutes' => 60,
                'responder_type' => 'ai_rag_agent',
                'ai_confidence_threshold' => 0.70,
                'ai_escalation_enabled' => true,
                'ai_escalation_phone' => '+59178311416',
                'ai_fallback_message' => $aiFallbackMsg,
            ]
        );
    }
}
