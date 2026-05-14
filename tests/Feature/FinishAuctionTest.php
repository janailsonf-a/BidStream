<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishAuctionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_auction_is_finished_and_winner_is_defined(): void
    {
        $seller = User::factory()->create();
        $buyerOne = User::factory()->create();
        $buyerTwo = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Notebook Dell Inspiron',
            'description' => 'Notebook usado em bom estado.',
            'starting_price' => 1500,
            'current_price' => 2500,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $buyerOne->id,
            'amount' => 2000,
        ]);

        Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $buyerTwo->id,
            'amount' => 2500,
        ]);

        $this->artisan('auctions:finish-expired')
            ->expectsOutput('Leilões finalizados: 1')
            ->assertSuccessful();

        $auction->refresh();

        $this->assertEquals('finished', $auction->status);
        $this->assertEquals($buyerTwo->id, $auction->winner_id);
    }

    public function test_expired_auction_without_bids_is_finished_without_winner(): void
    {
        $seller = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Mesa Gamer',
            'description' => 'Mesa para setup.',
            'starting_price' => 500,
            'current_price' => 500,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $this->artisan('auctions:finish-expired')
            ->expectsOutput('Leilões finalizados: 1')
            ->assertSuccessful();

        $auction->refresh();

        $this->assertEquals('finished', $auction->status);
        $this->assertNull($auction->winner_id);
    }

    public function test_non_expired_auction_is_not_finished(): void
    {
        $seller = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Cadeira Gamer',
            'description' => 'Cadeira em bom estado.',
            'starting_price' => 700,
            'current_price' => 700,
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $this->artisan('auctions:finish-expired')
            ->expectsOutput('Nenhum leilão expirado encontrado.')
            ->assertSuccessful();

        $auction->refresh();

        $this->assertEquals('active', $auction->status);
        $this->assertNull($auction->winner_id);
    }

    public function test_finished_auction_is_not_processed_again(): void
    {
        $seller = User::factory()->create();
        $winner = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'winner_id' => $winner->id,
            'title' => 'Monitor 4K',
            'description' => 'Monitor usado em bom estado.',
            'starting_price' => 1000,
            'current_price' => 1300,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'finished',
        ]);

        $this->artisan('auctions:finish-expired')
            ->expectsOutput('Nenhum leilão expirado encontrado.')
            ->assertSuccessful();

        $auction->refresh();

        $this->assertEquals('finished', $auction->status);
        $this->assertEquals($winner->id, $auction->winner_id);
    }

    public function test_oldest_bid_wins_when_highest_amount_is_tied(): void
    {
        $seller = User::factory()->create();
        $firstBidder = User::factory()->create();
        $secondBidder = User::factory()->create();

        $auction = Auction::create([
            'created_by' => $seller->id,
            'title' => 'Camera DSLR',
            'description' => 'Camera em bom estado.',
            'starting_price' => 1000,
            'current_price' => 2000,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $oldestBid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $firstBidder->id,
            'amount' => 2000,
        ]);
        $oldestBid->forceFill([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ])->save();

        $newestBid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $secondBidder->id,
            'amount' => 2000,
        ]);
        $newestBid->forceFill([
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ])->save();

        $this->artisan('auctions:finish-expired')
            ->expectsOutput('Leilões finalizados: 1')
            ->assertSuccessful();

        $this->assertEquals($firstBidder->id, $auction->fresh()->winner_id);
    }
}
