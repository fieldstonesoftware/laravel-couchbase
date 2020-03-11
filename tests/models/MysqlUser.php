<?php
namespace Fieldstone\Couchbase\Test\Model;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Schema;
use Fieldstone\Couchbase\Eloquent\HybridRelations;

class MysqlUser extends EloquentModel
{
    use HybridRelations;

    protected $connection = 'mysql';
    protected $table = 'users';
    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany('Book', 'author_id');
    }

    public function role()
    {
        return $this->hasOne('Role');
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');

        if (!$schema->hasTable('users')) {
            Schema::connection('mysql')->create('users', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
        }
    }
}
