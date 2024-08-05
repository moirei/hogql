<?php

namespace MOIREI\HogQl;

use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /** @inheritdoc */
    public function getDriverName()
    {
        return 'hogql';
    }

    /** @inheritdoc */
    public function query()
    {
        return new Builder($this);
    }
}
