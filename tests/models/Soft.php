<?php

use Fieldstone\Couchbase\Eloquent\Model as Eloquent;
use Fieldstone\Couchbase\Eloquent\SoftDeletes;

class Soft extends Eloquent
{
    use SoftDeletes;

    protected $connection = 'couchbase-not-default';
    protected $table = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
