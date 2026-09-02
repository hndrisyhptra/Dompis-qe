<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHasPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_admin_only_permission_via_union_rule(): void
    {
        $user = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->assertTrue($user->hasPermission('create_lop'));
    }

    public function test_admin_does_not_have_teknisi_only_permission(): void
    {
        $user = User::factory()->role(UserRole::ADMIN->value)->create();

        $this->assertFalse($user->hasPermission('upload_evidence'));
    }

    public function test_teknisi_has_upload_evidence_permission(): void
    {
        $user = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->assertTrue($user->hasPermission('upload_evidence'));
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::factory()->create(['role_id' => null]);

        $this->assertFalse($user->hasPermission('create_lop'));
    }

    public function test_unknown_permission_code_returns_false(): void
    {
        $user = User::factory()->role(UserRole::ADMIN->value)->create();

        $this->assertFalse($user->hasPermission('does_not_exist'));
    }
}
