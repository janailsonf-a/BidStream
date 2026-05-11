<?php

namespace App\Services;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuctionService
{
    public function placeBid(User $user, Auction $auction, float $amount): Bid
    {
        $lockKey = "auction:{$auction->id}:bid";

        $lock = Cache::lock($lockKey, 5);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'auction' => ['Outro lance está sendo processado neste leilão. Tente novamente em instantes.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $auction, $amount) {
                $auction = Auction::query()
                    ->whereKey($auction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->validateAuctionCanReceiveBid($user, $auction, $amount);

                $bid = Bid::create([
                    'auction_id' => $auction->id,
                    'user_id' => $user->id,
                    'amount' => $amount,
                ]);

                $auction->current_price = $amount;

                $secondsRemaining = now()->diffInSeconds($auction->ends_at, false);

                if ($secondsRemaining > 0 && $secondsRemaining <= 10) {
                    $auction->ends_at = $auction->ends_at->addSeconds(30);
                }

                $auction->save();

                event(new BidPlaced($bid->load('user:id,name,email'), $auction));

                return $bid->load('user:id,name,email');
            });
        } finally {
            optional($lock)->release();
        }
    }

    private function validateAuctionCanReceiveBid(User $user, Auction $auction, float $amount): void
    {
        if ($auction->created_by === $user->id) {
            throw ValidationException::withMessages([
                'auction' => ['O dono do leilão não pode dar lance no próprio item.'],
            ]);
        }

        if ($auction->status !== 'active') {
            throw ValidationException::withMessages([
                'auction' => ['Este leilão não está ativo.'],
            ]);
        }

        if (now()->lt($auction->starts_at)) {
            throw ValidationException::withMessages([
                'auction' => ['Este leilão ainda não começou.'],
            ]);
        }

        if (now()->gte($auction->ends_at)) {
            throw ValidationException::withMessages([
                'auction' => ['Este leilão já foi encerrado.'],
            ]);
        }

        if ($amount <= (float) $auction->current_price) {
            throw ValidationException::withMessages([
                'amount' => ['O lance precisa ser maior que o valor atual do leilão.'],
            ]);
        }
    }
}