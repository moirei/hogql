<?php

namespace MOIREI\HogQl;

use Illuminate\Database\Eloquent\Model as Base;

/**
 * @property string $created_at
 * @property string $distinct_id
 * @property string $elements_chain
 * @property string $event
 * @property array  $properties
 * @property string $team_id
 * @property string $timestamp
 * @property string $uuid
 */
class Model extends Base
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'hogql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'timestamp';
}
