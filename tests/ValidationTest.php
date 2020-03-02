<?php
namespace Fieldstone\Couchbase\Test;

use User;

class ValidationTest extends TestCase
{
    public function tearDown() : void
    {
        User::truncate();
    }

    public function testUnique()
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:couchbase-not-default.users']
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'John Doe']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:couchbase-not-default.users']
        );
        $this->assertTrue($validator->fails());
    }
}
