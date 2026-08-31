<?php

return [
    'dispatch_mode' => env('CRM_DISPATCH_MODE', 'sync'),
    'queues' => [
        'whatsapp' => env('CRM_QUEUE_WHATSAPP', 'whatsapp'),
        'automations' => env('CRM_QUEUE_AUTOMATIONS', 'automations'),
    ],
];
