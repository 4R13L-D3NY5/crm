<?php

namespace App\Modules\Docs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiDocsController extends Controller
{
    public function openApiJson(): JsonResponse
    {
        $doc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Whaticket - API',
                'version' => '1.0.0',
                'description' => 'This API provides an interface for sending messages through connections connected to Whaticket. All routes require a Bearer token. You can create one on Tokens page.',
            ],
            'servers' => [
                ['url' => url('/api/v1'), 'description' => 'Current Environment Server'],
                ['url' => 'https://api.whaticket.com/api/v1', 'description' => 'Production Server'],
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
            'security' => [
                ['BearerAuth' => []],
            ],
            'paths' => [
                '/contacts' => [
                    'get' => [
                        'tags' => ['Contacts'],
                        'summary' => 'List contacts',
                        'responses' => ['200' => ['description' => 'Successful operation']],
                    ],
                    'post' => [
                        'tags' => ['Contacts'],
                        'summary' => 'Creates a new contact',
                        'responses' => ['201' => ['description' => 'Contact created']],
                    ],
                ],
                '/contacts/create-or-update' => [
                    'post' => [
                        'tags' => ['Contacts'],
                        'summary' => 'Create or update a contact',
                        'responses' => ['200' => ['description' => 'Contact created or updated']],
                    ],
                ],
                '/whatsapps' => [
                    'get' => [
                        'tags' => ['WhatsApps'],
                        'summary' => 'List whatsapp connections',
                        'responses' => ['200' => ['description' => 'Successful operation']],
                    ],
                ],
                '/whatsapps/start/{id}' => [
                    'patch' => [
                        'tags' => ['WhatsApps'],
                        'summary' => 'Restarts a whatsapp session',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'Session restarted']],
                    ],
                ],
                '/messages/send' => [
                    'post' => [
                        'tags' => ['Messages'],
                        'summary' => 'Send a text or media message',
                        'responses' => ['200' => ['description' => 'Message sent successfully']],
                    ],
                ],
            ],
        ];

        return response()->json($doc);
    }
}
