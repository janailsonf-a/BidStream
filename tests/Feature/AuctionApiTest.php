<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_auction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auctions', $this->validAuctionPayload());

        $response->assertCreated()
            ->assertJsonPath('auction.created_by', $user->id)
            ->assertJsonPath('auction.title', 'Notebook Dell Inspiron')
            ->assertJsonPath('auction.current_price', '1500.00');

        $this->assertDatabaseHas('auctions', [
            'created_by' => $user->id,
            'title' => 'Notebook Dell Inspiron',
            'starting_price' => 1500,
            'current_price' => 1500,
            'status' => 'draft',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_auction(): void
    {
        $this->postJson('/api/auctions', $this->validAuctionPayload())
            ->assertUnauthorized();
    }

    public function test_create_auction_requires_core_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auctions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'starting_price',
                'starts_at',
                'ends_at',
            ]);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addHour();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auctions', $this->validAuctionPayload([
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->subMinute()->toDateTimeString(),
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_authenticated_user_can_list_auctions(): void
    {
        $user = User::factory()->create();
        Auction::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auctions');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'creator'],
                ],
            ]);
    }

    public function test_status_filter_returns_only_matching_auctions(): void
    {
        $user = User::factory()->create();
        Auction::factory()->create(['status' => 'draft', 'title' => 'Draft item']);
        Auction::factory()->active()->create(['status' => 'active', 'title' => 'Active item']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auctions?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.title', 'Active item');
    }

    public function test_authenticated_user_can_show_auction_details(): void
    {
        $user = User::factory()->create();
        $auction = Auction::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('auction.id', $auction->id)
            ->assertJsonStructure([
                'auction' => ['creator', 'winner', 'bids'],
            ]);
    }

    public function test_owner_can_update_draft_auction(): void
    {
        $owner = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/auctions/{$auction->id}", $this->validAuctionPayload([
                'title' => 'Updated auction',
                'starting_price' => 2100,
            ]));

        $response->assertOk()
            ->assertJsonPath('auction.title', 'Updated auction')
            ->assertJsonPath('auction.current_price', '2100.00');

        $this->assertDatabaseHas('auctions', [
            'id' => $auction->id,
            'title' => 'Updated auction',
            'current_price' => 2100,
        ]);
    }

    public function test_non_owner_cannot_update_auction(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/auctions/{$auction->id}", $this->validAuctionPayload([
                'title' => 'Forbidden update',
            ]));

        $response->assertForbidden();
    }

    public function test_non_draft_auction_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/auctions/{$auction->id}", $this->validAuctionPayload());

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Apenas leilões em rascunho podem ser editados.');
    }

    public function test_owner_can_delete_draft_auction(): void
    {
        $owner = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Leilão excluído com sucesso.');

        $this->assertDatabaseMissing('auctions', [
            'id' => $auction->id,
        ]);
    }

    public function test_non_owner_cannot_delete_auction(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $owner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/auctions/{$auction->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('auctions', [
            'id' => $auction->id,
        ]);
    }

    public function test_non_draft_auction_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/auctions/{$auction->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Apenas leilões em rascunho podem ser excluídos.');

        $this->assertDatabaseHas('auctions', [
            'id' => $auction->id,
        ]);
    }

    private function validAuctionPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ], $overrides);
    }
}
