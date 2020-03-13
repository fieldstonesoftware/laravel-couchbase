<?php
namespace Fieldstone\Couchbase\Test\Model;

use Fieldstone\Couchbase\Eloquent\Model as CBModel;

class Book extends CBModel
{
    protected $connection = 'couchbase-not-default';
    protected static $unguarded = true;

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mysqlAuthor()
    {
        return $this->belongsTo(MysqlUser::class, 'author_id');
    }
}
