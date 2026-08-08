<?php

namespace Tests\Feature;

use App\Models\Funder;
use App\Models\User;
use Tests\TestCase;

class FundersDataTableTest extends TestCase
{
    public function test_funders_datatable_paginates_server_side(): void
    {
        $user = User::query()->where('super_admin', 1)->first() ?? User::first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $prefix = 'DT-PAG-' . uniqid() . '-';

        for ($i = 1; $i <= 12; $i++) {
            Funder::create(['name' => $prefix . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->getJson(route('dashboard.funders.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 5,
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => false, 'search' => ['value' => '', 'regex' => false]],
            ],
            'search' => ['value' => '', 'regex' => false],
            'order' => [],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(5, count($payload['data'] ?? []));
        $this->assertGreaterThanOrEqual(12, $payload['recordsTotal'] ?? 0);
        $this->assertGreaterThanOrEqual(12, $payload['recordsFiltered'] ?? 0);

        $pageTwo = $this->getJson(route('dashboard.funders.index', [
            'draw' => 2,
            'start' => 5,
            'length' => 5,
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => false, 'search' => ['value' => '', 'regex' => false]],
            ],
            'search' => ['value' => '', 'regex' => false],
            'order' => [],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $pageTwo->assertOk();
        $pageTwoPayload = $pageTwo->json();
        $this->assertSame(5, count($pageTwoPayload['data'] ?? []));

        Funder::query()->where('name', 'like', $prefix . '%')->delete();
    }

    public function test_funders_index_page_renders_datatable_shell(): void
    {
        $user = User::query()->where('super_admin', 1)->first() ?? User::first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $this->get(route('dashboard.funders.index'))
            ->assertOk()
            ->assertSee('funders-table', false)
            ->assertSee('advanced-pagination', false);
    }
}
