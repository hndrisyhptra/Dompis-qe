<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TicketSegmentMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketSegmentMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ticket_segment_maps'));
        $this->assertTrue(Schema::hasColumns('ticket_segment_maps', [
            'id_ticket_segment_map', 'source_value', 'segment', 'created_at', 'updated_at',
        ]));
    }

    public function test_super_admin_can_manage_mappings(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->actingAs($superAdmin)->get(route('ticket-segment-maps.index'))->assertOk();

        $this->actingAs($superAdmin)
            ->post(route('ticket-segment-maps.store'), ['source_value' => 'gamas odp', 'segment' => 'odp'])
            ->assertRedirect(route('ticket-segment-maps.index'));

        // source_value dinormalisasi ke huruf kapital.
        $this->assertDatabaseHas('ticket_segment_maps', ['source_value' => 'GAMAS ODP', 'segment' => 'odp']);

        $map = TicketSegmentMap::firstWhere('source_value', 'GAMAS ODP');

        $this->actingAs($superAdmin)
            ->put(route('ticket-segment-maps.update', $map), ['source_value' => 'GAMAS ODP', 'segment' => 'feeder'])
            ->assertRedirect(route('ticket-segment-maps.index'));
        $this->assertDatabaseHas('ticket_segment_maps', ['id_ticket_segment_map' => $map->id_ticket_segment_map, 'segment' => 'feeder']);

        $this->actingAs($superAdmin)
            ->delete(route('ticket-segment-maps.destroy', $map))
            ->assertRedirect(route('ticket-segment-maps.index'));
        $this->assertDatabaseMissing('ticket_segment_maps', ['id_ticket_segment_map' => $map->id_ticket_segment_map]);
    }

    public function test_duplicate_source_value_is_rejected(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        TicketSegmentMap::create(['source_value' => 'SQM', 'segment' => 'feeder']);

        $this->actingAs($superAdmin)
            ->post(route('ticket-segment-maps.store'), ['source_value' => 'sqm', 'segment' => 'odp'])
            ->assertSessionHasErrors('source_value');
    }

    public function test_invalid_segment_is_rejected(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->actingAs($superAdmin)
            ->post(route('ticket-segment-maps.store'), ['source_value' => 'REG', 'segment' => 'not-a-segment'])
            ->assertSessionHasErrors('segment');
    }

    public function test_non_super_admin_roles_cannot_access(): void
    {
        foreach ([UserRole::ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();

            $this->actingAs($user)->get(route('ticket-segment-maps.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('ticket-segment-maps.store'), ['source_value' => 'X', 'segment' => 'odp'])
                ->assertForbidden();
        }
    }
}
