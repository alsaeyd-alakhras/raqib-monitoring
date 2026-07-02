<?php

namespace Tests\Feature;

use App\Models\MonitoringActivity;
use App\Models\User;
use Tests\TestCase;

class MonitoringActivitiesCreateEditSmokeTest extends TestCase
{
    public function test_create_and_edit_pages_render(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $this->get('/monitoring-activities/create')->assertStatus(200);

        $activity = MonitoringActivity::first();
        if ($activity) {
            $this->get(route('dashboard.monitoring-activities.edit', $activity))->assertStatus(200);
        }
    }
}
