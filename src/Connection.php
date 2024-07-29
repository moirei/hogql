<?php

namespace MOIREI\HogQl;

use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    public function query()
    {
        return new Builder($this);
    }
}
