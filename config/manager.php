<?php

return [
    /**
     * The default queue that should be used to process the key.
     * Should be set to false if no queue want to be used.
     *
     * @default default
     */
    'queue' => 'default',

    /**
     * This defines if when fails to generate or fetch credentials the system goes into maintenance mode.
     * If saving new credentials while existing valid ones fails wont be affected by this setting.
     *
     * @default true
     */
    'strict' => true,

    /**
     * The motor that will be used to store the credentials db.
     */
    'index_file' => 'index.yml',

    /**
     * This is the default filesystem storage unit.
     * This should be defined according what your storage supports and your own needs.
     *
     * @default secure
     */
    'storage' => 'secure',


    /**
     * This is the default local filesystem storage unit.
     *
     * @default local
     */
    'local_storage' => 'local',

    /**
     * Certificates remote directory
     */
    'remote_path' => 'keys',

    /**
     * Credentials configuration
     */
    'credentials' => [
        'root' => [
            'type' => \phpseclib\Crypt\RSA::class,
            'password' => true,
            'path' => 'roots',
            'filename' => 'root',
            'size' => 4096,
            'keep' => 1,
            'sync' => ['pub']
        ],
        'oauth' => [
            'type' => \phpseclib\Crypt\RSA::class,
            'password' => false,
            'path' => 'oauth',
            'filename' => 'oauth',
            'size' => 4096,
            'keep' => 3,
            'sync' => ['*']
        ],
        'webhooks' => [
            'type' => \phpseclib\Crypt\RSA::class,
            'password' => false,
            'path' => 'webhooks',
            'filename' => 'webhook',
            'size' => 4096,
            'keep' => 1,
            'sync' => ['*']
        ]
    ]
];
