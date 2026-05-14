<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuctionBidTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_valid_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 1500,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1600,
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Lance realizado com sucesso.')
            ->assertJsonPath('auction.current_price', '1600.00');

        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'amount' => 1600,
        ]);

        $this->assertEquals('1600.00', $auction->fresh()->current_price);
    }

    public function test_bid_must_be_greater_than_current_price(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 1600,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1500,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseMissing('bids', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'amount' => 1500,
        ]);

        $this->assertEquals('1600.00', $auction->fresh()->current_price);
    }

    public function test_owner_cannot_bid_on_own_auction(): void
    {
        Event::fake();

        $seller = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 1500,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1700,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);

        $this->assertDatabaseMissing('bids', [
            'auction_id' => $auction->id,
            'user_id' => $seller->id,
            'amount' => 1700,
        ]);
    }

    public function test_finished_auction_cannot_receive_bid(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 1500,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 1800,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['auction']);

        $this->assertDatabaseMissing('bids', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'amount' => 1800,
        ]);
    }

    public function test_last_second_bid_extends_auction_time(): void
    {
        Event::fake();

        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $originalEndsAt = now()->addSeconds(8);

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 1500,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => $originalEndsAt,
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 2000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Lance realizado com sucesso.');

        $auction->refresh();

        $this->assertEquals('2000.00', $auction->current_price);

        $this->assertTrue(
            $auction->ends_at->greaterThan($originalEndsAt),
            'O ends_at deveria ser estendido após lance nos últimos segundos.'
        );

        $this->assertEquals(
            $originalEndsAt->copy()->addSeconds(30)->timestamp,
            $auction->ends_at->timestamp
        );
    }
}