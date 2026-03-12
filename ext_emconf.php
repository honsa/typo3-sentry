<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 Sentry Logger',
    'description' => 'TYPO3 log writer that forwards TYPO3 log records to Sentry.',
    'category' => 'services',
    'author' => 'Honsa',
    'author_email' => '',
    'author_company' => 'Honsa',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

