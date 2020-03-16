<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;
use Fieldstone\Couchbase\Eloquent\SoftDeletes as CBSoftDeletes;

class Soft extends CBModel
{
    use CBSoftDeletes;

    protected $connection = 'couchbase-not-default';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
