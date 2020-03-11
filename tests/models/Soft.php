<?php

use Fieldstone\Couchbase\Eloquent\Model as CBModel;
use Fieldstone\Couchbase\Eloquent\SoftDeletes as CBSoftDeletes;

class Soft extends CBModel
{
    use CBSoftDeletes;

    protected $connection = 'couchbase-not-default';
    protected $table = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
