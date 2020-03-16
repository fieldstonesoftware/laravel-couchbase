<?php
namespace Fieldstone\Couchbase\Test;

use Fieldstone\Couchbase\Test\Model\User;
use Illuminate\Support\Facades\DB;

class PaginationTest extends TestCase
{
    public function tearDown() : void
    {
        User::truncate();
        parent::tearDown();
    }

    /**
     * @group PaginationTest
     */
    public function testAll()
    {
        DB::connection('couchbase-not-default')->table('user')->delete();

        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(6, $pagination->total());
    }

    /**
     * @group PaginationTest
     */
    public function testWhere()
    {
        DB::connection('couchbase-not-default')->table('user')->delete();

        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
    }

    /**
     * @group PaginationTest
     */
    public function testOrderBy()
    {
        DB::connection('couchbase-not-default')->table('user')->delete();

        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::connection('couchbase-not-default')->table('user')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'ASC')->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 3', $pagination->get(0)->name);
        $this->assertEquals('John Doe 4', $pagination->get(1)->name);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'ASC')->paginate(2, ['*'], 'page', 2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 5', $pagination->get(0)->name);
        $this->assertEquals('John Doe 6', $pagination->get(1)->name);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'DESC')->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 6', $pagination->get(0)->name);
        $this->assertEquals('John Doe 5', $pagination->get(1)->name);
    }
}
