<?php

namespace App\Shared\Support;

class RolePermissions
{
    public const ALL = [
        'dashboard.view',
        'reports.view',
        'audit.view',
        'organizations.switch',
        'users.view',
        'contacts.view',
        'contacts.manage',
        'companies.view',
        'companies.manage',
        'deals.view',
        'deals.manage',
        'pipelines.view',
        'pipelines.manage',
        'conversations.view',
        'conversations.manage',
        'conversations.assign',
        'conversations.status.manage',
        'whatsapp.view',
        'whatsapp.manage',
        'automations.view',
        'automations.manage',
        'ai.use',
    ];

    public static function forRole(?string $role): array
    {
        return match ($role) {
            'owner', 'admin' => self::ALL,
            'agent', 'member' => [
                'dashboard.view',
                'reports.view',
                'organizations.switch',
                'users.view',
                'contacts.view',
                'contacts.manage',
                'companies.view',
                'companies.manage',
                'deals.view',
                'deals.manage',
                'pipelines.view',
                'conversations.view',
                'conversations.manage',
                'conversations.assign',
                'conversations.status.manage',
                'whatsapp.view',
                'whatsapp.manage',
                'automations.view',
                'ai.use',
            ],
            'viewer' => [
                'dashboard.view',
                'reports.view',
                'organizations.switch',
                'users.view',
                'contacts.view',
                'companies.view',
                'deals.view',
                'pipelines.view',
                'conversations.view',
                'whatsapp.view',
            ],
            default => [],
        };
    }
}
