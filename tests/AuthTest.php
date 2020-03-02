<?php
namespace Fieldstone\Couchbase\Test;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use User;

class AuthTest extends TestCase
{
    public function tearDown() : void
    {
        User::truncate();
        DB::table('password_reminders')->truncate();
    }

    public function testAuthAttempt()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'password' => Hash::make('foobar'),
        ]);

        $this->assertTrue(Auth::attempt(['email' => 'john@doe.com', 'password' => 'foobar'], true));
        $this->assertTrue(Auth::check());
    }
}
