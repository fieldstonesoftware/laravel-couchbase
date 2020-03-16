<?php
namespace Fieldstone\Couchbase\Test;

use Fieldstone\Couchbase\Test\Model\User;
use Fieldstone\Couchbase\Test\Seeds\UserTableSeeder;
use Illuminate\Support\Facades\Artisan;

class SeederTest extends TestCase
{
    public function tearDown() : void
    {
        User::truncate();
    }

    public function testSeed()
    {
        $seeder = new UserTableSeeder;
        $seeder->run();

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }

    public function testArtisan()
    {
        Artisan::call('db:seed', ['--class' => '\Fieldstone\Couchbase\Test\Seeds\DatabaseSeeder']);

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }
}
