<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Photo extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected static $unguarded = true;

    public function imageable()
    {
        return $this->morphTo();
    }
}
