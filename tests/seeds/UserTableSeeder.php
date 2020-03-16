<?php
namespace Fieldstone\Couchbase\Test\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{
    public function run()
    {
        DB::connection('couchbase-not-default')->table('user')->delete();

        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe', 'seed' => true]);
    }
}
