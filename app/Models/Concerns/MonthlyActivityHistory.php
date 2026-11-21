<?php

namespace App\Models\Concerns;

use LogicException;

abstract class MonthlyActivityHistory extends MonthlyActivityRecord
{
    public $timestamps = false;

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException('Monthly activity history is append-only.');
        }

        return parent::save($options);
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('Monthly activity history is append-only.');
    }
}
