<?php

namespace Tests\Unit;

use App\Support\PostedIdList;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PostedIdListTest extends TestCase
{
    public function test_json_ids_become_a_positive_unique_list(): void
    {
        $request = Request::create('/batches', 'POST', [
            'domain_ids_json' => '[12, "34", 0, -1, "no", 34, "12"]',
        ]);

        PostedIdList::merge($request, 'domain_ids', 'domain_ids_json');

        $this->assertSame([12, 34], $request->input('domain_ids'));
    }

    public function test_invalid_json_becomes_an_empty_list(): void
    {
        $request = Request::create('/batches', 'POST', [
            'domain_ids_json' => '{',
        ]);

        PostedIdList::merge($request, 'domain_ids', 'domain_ids_json');

        $this->assertSame([], $request->input('domain_ids'));
    }

    public function test_checkbox_array_is_kept(): void
    {
        $request = Request::create('/batches', 'POST', [
            'domain_ids' => [7, 8],
            'domain_ids_json' => '[1]',
        ]);

        PostedIdList::merge($request, 'domain_ids', 'domain_ids_json');

        $this->assertSame([7, 8], $request->input('domain_ids'));
    }
}
