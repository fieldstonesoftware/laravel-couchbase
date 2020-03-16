<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Address extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }
}
