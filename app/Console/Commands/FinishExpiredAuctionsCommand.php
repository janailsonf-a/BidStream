<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Services\AuctionService;
use Illuminate\Console\Command;

class FinishExpiredAuctionsCommand extends Command
{
    protected $signature = 'auctions:finish-expired';

    protected $description = 'Finaliza leilões expirados e define o vencedor com base no maior lance.';

    public function handle(AuctionService $auctionService): int
    {
        $auctions = Auction::query()
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        if ($auctions->isEmpty()) {
            $this->info('Nenhum leilão expirado encontrado.');
            return self::SUCCESS;
        }

        $finishedCount = 0;

        foreach ($auctions as $auction) {
            $auctionService->finishAuction($auction);
            $finishedCount++;
        }

        $this->info("Leilões finalizados: {$finishedCount}");

        return self::SUCCESS;
    }
}