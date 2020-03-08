<?php

namespace Fieldstone\Couchbase\Test;

use Fieldstone\Couchbase\Eloquent\Model;
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
        $dataType = 'users';

        $query = DB::table($dataType)->select();
        $this->assertEquals(
            'select `'
            . $query->from
            . '`.*, meta(`'.$query->from.'`).`id` as `_id`'
            . ' from `'.$query->from.'`'
            . ' where `'.Model::getDocumentTypeKeyName().'` = ?',
            $query->toSql()
        );

        // assert the data type used in select is the same in the bindings
        $this->assertEquals(
            $dataType
            ,$query->getBindings()[0]
        );
    }
}
