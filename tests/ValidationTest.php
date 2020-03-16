<?php
namespace Fieldstone\Couchbase\Test;

use Fieldstone\Couchbase\Test\Model\User;
use Illuminate\Support\Facades\Validator;

class ValidationTest extends TestCase
{
    public function tearDown() : void
    {
        User::truncate();
    }

    public function testUnique()
    {
        $validator = Validator::make(['name' => 'John Doe'],    // data
            ['name' => 'required|unique:couchbase-not-default.user'] // rules
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'John Doe']);

        $validator = Validator::make( ['name' => 'John Doe'],   // data
            ['name' => 'required|unique:couchbase-not-default.user'] // rules
        );
        $this->assertTrue($validator->fails());
    }
}
