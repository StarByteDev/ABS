<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_user_cannot_open_private_portal(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)->get('/private')->assertForbidden();
    }

    public function test_active_private_member_can_open_private_portal(): void
    {
        $user = User::factory()->create(['role' => 'private_member', 'status' => 'active', 'private_member_approved_at' => now()]);
        $this->actingAs($user)->get('/private')->assertOk();
    }
}
