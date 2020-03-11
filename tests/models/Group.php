<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Group extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected $table = 'groups';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User', null, 'groups', 'users');
    }
}
