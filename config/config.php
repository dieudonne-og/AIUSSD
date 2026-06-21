<?php
// Central app + DB config. Copy of values the whole app reads.
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'digital_inclusion',
        'user' => 'di_app',          // dedicated app user (root uses socket auth)
        'pass' => 'di_pass_2026',    // created during environment setup
        'charset' => 'utf8mb4',
    ],
    // Default dashboard official created by seed.php
    'admin' => [
        'username' => 'official',
        'password' => 'changeme123',
    ],
];
