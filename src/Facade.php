<?php

namespace MOIREI\HogQl;

use Illuminate\Support\Facades\Facade as Base;

/**
 * @method static \Illuminate\Database\Query\Builder query(?string $table = null)
 * @method static \Illuminate\Database\Query\Builder select($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder model()
 * @method static \Illuminate\Database\Eloquent\Builder eloquent()
 * @method static \Illuminate\Database\Query\Builder parse(string $query)
 * @method static array get(string $query)
 */
class Facade extends Base
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'hogql';
    }
}
