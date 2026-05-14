<?php

namespace Tests\Feature;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BidApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_place_valid_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
            'starting_price' => 1500,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertCreated()
            ->assertJsonPath('bid.user.id', $buyer->id)
            ->assertJsonPath('bid.amount', '1600.00')
            ->assertJsonPath('auction.current_price', '1600.00');

        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'amount' => 1600,
        ]);

        $this->assertEquals('1600.00', $auction->fresh()->current_price);
    }

    public function test_unauthenticated_user_cannot_place_bid(): void
    {
        $auction = Auction::factory()->active()->create();

        $this->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 1600,
        ])->assertUnauthorized();
    }

    public function test_owner_cannot_bid_on_own_auction(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);

        $this->assertDatabaseCount('bids', 0);
    }

    public function test_bid_amount_must_be_greater_than_current_price(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1500,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('bids', 0);
    }

    public function test_non_active_auction_cannot_receive_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $seller->id,
            'status' => 'draft',
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'current_price' => 1500,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);
    }

    public function test_auction_that_has_not_started_cannot_receive_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->create([
            'created_by' => $seller->id,
            'status' => 'active',
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addHour(),
            'current_price' => 1500,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);
    }

    public function test_ended_auction_cannot_receive_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->expired()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);
    }

    public function test_valid_bid_creates_record_and_updates_current_price(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1800,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'amount' => 1800,
        ]);
        $this->assertEquals('1800.00', $auction->fresh()->current_price);
    }

    public function test_bid_listing_returns_bids_with_users(): void
    {
        $viewer = User::factory()->create();
        $bidder = User::factory()->create();
        $auction = Auction::factory()->active()->create();

        Bid::factory()->create([
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => 1600,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}/bids");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $bidder->id)
            ->assertJsonPath('data.0.amount', '1600.00');
    }

    public function test_last_second_bid_extends_auction_by_thirty_seconds(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $originalEndsAt = now()->addSeconds(8);
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
            'ends_at' => $originalEndsAt,
        ]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 2000,
            ])
            ->assertCreated();

        $this->assertEquals(
            $originalEndsAt->copy()->addSeconds(30)->timestamp,
            $auction->fresh()->ends_at->timestamp
        );
    }

    public function test_active_bid_lock_returns_error_and_allows_bid_after_release(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $lock = Cache::lock("auction:{$auction->id}:bid", 5);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($buyer, 'sanctum')
                ->postJson("/api/auctions/{$auction->id}/bids", [
                    'amount' => 1600,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['auction']);
        } finally {
            $lock->release();
        }

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ])
            ->assertCreated();
    }

    public function test_bid_placed_event_is_dispatched_with_broadcast_payload(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = Auction::factory()->active()->create([
            'created_by' => $seller->id,
            'current_price' => 1500,
        ]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ])
            ->assertCreated();

        Event::assertDispatched(BidPlaced::class, function (BidPlaced $event) use ($auction, $buyer) {
            $payload = $event->broadcastWith();

            return $payload['auction_id'] === $auction->id
                && $payload['current_price'] === '1600.00'
                && array_key_exists('ends_at', $payload)
                && $payload['last_bid']['amount'] === '1600.00'
                && $payload['last_bid']['user']['id'] === $buyer->id;
        });
    }
}
