<?php

namespace Fieldstone\Couchbase\Test;

use Illuminate\Support\Facades\DB;

class ArgsParametersTest extends TestCase
{
    public function setUp() : void
    {
        putenv('CB_INLINE_PARAMETERS=false');
        parent::setUp();
    }

    /**
     * @group ArgsParametersTest
     * @group ParametersTest
     */
    public function testParameters()
    {
        $query = DB::table('table6')->select();

        $this->assertEquals(false, config('database.connections.couchbase.inline_parameters'));
        $this->assertEquals(false, DB::hasInlineParameters());
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = ?',
            $query->toSql());
    }
}
