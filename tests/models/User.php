<?php

namespace Fieldstone\Couchbase\Test\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class User extends CBModel implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    protected $connection = 'couchbase-not-default';
    protected $dates = ['birthday', 'entry.date'];
    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function mysqlBooks()
    {
        return $this->hasMany(MysqlBook::class, 'author_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function role()
    {
        return $this->hasOne(Role::class);
    }

    public function mysqlRole()
    {
        return $this->hasOne(MysqlRole::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
//        return $this->belongsToMany(Client::class, null, 'user_ids', 'client_ids');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, null, 'users', 'groups');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'imageable');
    }

    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }

    public function father()
    {
        return $this->embedsOne(User::class);
    }
}
