<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_soft_delete_and_nullable_email(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
    }

    public function test_user_histories_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('user_histories'));
        $this->assertTrue(Schema::hasColumns('user_histories', [
            'id_user_history', 'target_user_id', 'actor_id',
            'event_type', 'changes', 'note', 'created_at', 'updated_at',
        ]));
    }
}
