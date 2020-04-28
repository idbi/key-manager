<?php

namespace ID\KeyManager;

use ID\KeyManager\Services\KeyManagerService;
use Illuminate\Support\Facades\Facade;

/**
 * @see \ID\KeyManager\SkeletonClass
 */
class KeyManagerFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return KeyManagerService::class;
    }
}
