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
     * This is the default filesystem storage unit.
     * This should be defined according what your storage supports and your own needs.
     *
     * @default secure
     */
    'storage' => 'secure',

];
