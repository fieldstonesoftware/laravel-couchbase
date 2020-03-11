<?php

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Role extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected $table = 'roles';
    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo('User');
    }

    public function mysqlUser()
    {
        return $this->belongsTo('MysqlUser');
    }
}
