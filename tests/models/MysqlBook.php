<?php
namespace Fieldstone\Couchbase\Test\Model;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Schema;
use Fieldstone\Couchbase\Eloquent\HybridRelations;

class MysqlBook extends EloquentModel
{
    use HybridRelations;

    protected $connection = 'mysql';
    protected $table = 'books';
    protected static $unguarded = true;
    protected $primaryKey = 'title';

    public function author()
    {
        return $this->belongsTo('User', 'author_id');
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');

        if (!$schema->hasTable('books')) {
            Schema::connection('mysql')->create('books',
                function ($table) {
                    $table->string('title');
                    $table->string('author_id');
                    $table->timestamps();
                });
        }
    }
}
