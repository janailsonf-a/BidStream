<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceBidRequest;
use App\Models\Auction;
use App\Services\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function index(Auction $auction): JsonResponse
    {
        $bids = $auction->bids()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(20);

        return response()->json($bids);
    }

    public function store(
        PlaceBidRequest $request,
        Auction $auction,
        AuctionService $auctionService
    ): JsonResponse {
        $bid = $auctionService->placeBid(
            user: $request->user(),
            auction: $auction,
            amount: (float) $request->validated('amount')
        );

        return response()->json([
            'message' => 'Lance realizado com sucesso.',
            'bid' => $bid,
            'auction' => $auction->fresh(),
        ], 201);
    }
}