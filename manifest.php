<?php

declare(strict_types=1);

/**
 * MANIFEST modul Rfq.
 *
 * Dokumen RFQ: customer membuat RFQ dari equipment-nya, dikirim ke surveyor,
 * surveyor mengisi rate lalu accept/decline. Status: draft|sent|accepted|
 * declined|expired (workflow engine = map transisi di model, lihat Rfq.php).
 */
return [
    'menu' => [
        [
            'slug'       => 'rfqs',
            'label'      => 'RFQs',
            'icon'       => '📄',
            'href'       => '/rfqs',
            'position'   => 42,
            'permission' => 'rfq:view|rfq:view_own',
        ],
    ],

    'settings' => [
        [
            'slug'     => 'rfq',
            'label'    => 'RFQ',
            'icon'     => '📄',
            'position' => 50,
            'fields'   => [
                ['key' => 'rfq_prefix',       'label' => 'Prefix',      'type' => 'text',   'default' => 'RFQ-'],
                ['key' => 'rfq_number_length', 'label' => 'Panjang Nomor', 'type' => 'number', 'default' => '5'],
            ],
        ],
    ],

    'detail_tabs' => [
        [
            'slug'       => 'overview',
            'label'      => 'Overview',
            'icon'       => '👁️',
            'api'        => '/api/v1/rfqs/{id}',
            'position'   => 10,
            'permission' => 'rfq:view|rfq:view_own',
        ],
        [
            'slug'       => 'activity',
            'label'      => 'Activity',
            'icon'       => '🕐',
            'api'        => '/api/v1/rfqs/{id}/activity-logs',
            'position'   => 20,
            'permission' => 'rfq:view|rfq:view_own',
        ],
    ],

    'rbac' => [
        'permissions' => [
            'rfq:view', 'rfq:view_own', 'rfq:create', 'rfq:edit',
            'rfq:edit_own', 'rfq:delete', 'rfq:mark_as', 'rfq:convert_to_quotation',
        ],
        'roles' => [
            ['name' => 'rfq-admin', 'label' => 'Rfq Admin',
             'permissions' => ['rfq:*']],
        ],
        'grants' => [
            'customer'              => ['rfq:view_own'],
            'customer-admin'        => ['rfq:view', 'rfq:create', 'rfq:edit', 'rfq:mark_as'],
            'customer-branch-admin' => ['rfq:view_own', 'rfq:create', 'rfq:edit_own', 'rfq:mark_as'],
            'surveyor'              => ['rfq:view_own'],
            'surveyor-admin'        => ['rfq:view', 'rfq:mark_as', 'rfq:convert_to_quotation'],
            'surveyor-branch-admin' => ['rfq:view_own', 'rfq:mark_as', 'rfq:convert_to_quotation'],
            'platform-finance'      => ['rfq:view'],
            'platform-cs'           => ['rfq:view', 'rfq:create', 'rfq:edit', 'rfq:mark_as', 'rfq:convert_to_quotation'],
            'platform-it'           => ['rfq:view'],
        ],
    ],
];
