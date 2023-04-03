<?php

namespace Jaulz\Inventarium\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Jaulz\Inventarium\Inventarium
 */
class Inventarium extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Jaulz\Inventarium\Inventarium::class;
    }
}