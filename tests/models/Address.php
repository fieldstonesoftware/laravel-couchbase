<?php

use Fieldstone\Couchbase\Eloquent\Model as Eloquent;

class Address extends Eloquent
{
    protected $connection = 'couchbase-not-default';
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany('Address');
    }
}
