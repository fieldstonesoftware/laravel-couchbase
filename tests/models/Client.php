<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Client extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected $table = 'clients';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User');
    }

    public function photo()
    {
        return $this->morphOne('Photo', 'imageable');
    }

    public function addresses()
    {
        return $this->hasMany('Address', 'data.address_id', 'data.client_id');
    }
}
