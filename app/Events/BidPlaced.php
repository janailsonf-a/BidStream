<?php

namespace App\Events;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Bid $bid,
        public Auction $auction
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('auction.' . $this->auction->id);
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auction->id,
            'current_price' => $this->auction->current_price,
            'ends_at' => $this->auction->ends_at?->toDateTimeString(),
            'last_bid' => [
                'id' => $this->bid->id,
                'amount' => $this->bid->amount,
                'created_at' => $this->bid->created_at?->toDateTimeString(),
                'user' => [
                    'id' => $this->bid->user?->id,
                    'name' => $this->bid->user?->name,
                    'email' => $this->bid->user?->email,
                ],
            ],
        ];
    }
}