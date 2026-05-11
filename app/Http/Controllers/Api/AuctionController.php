<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuctionRequest;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $auctions = Auction::query()
            ->with(['creator:id,name,email', 'winner:id,name,email'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(10);

        return response()->json($auctions);
    }

    public function store(StoreAuctionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $auction = Auction::create([
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starting_price' => $data['starting_price'],
            'current_price' => $data['starting_price'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['status'] ?? 'draft',
        ]);

        return response()->json([
            'message' => 'Leilão criado com sucesso.',
            'auction' => $auction->load(['creator:id,name,email']),
        ], 201);
    }

    public function show(Auction $auction): JsonResponse
    {
        return response()->json([
            'auction' => $auction->load([
                'creator:id,name,email',
                'winner:id,name,email',
                'bids' => function ($query) {
                    $query->with('user:id,name,email')
                        ->latest()
                        ->limit(20);
                },
            ]),
        ]);
    }

    public function update(StoreAuctionRequest $request, Auction $auction): JsonResponse
    {
        if ($auction->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Você não tem permissão para editar este leilão.',
            ], 403);
        }

        if ($auction->status !== 'draft') {
            return response()->json([
                'message' => 'Apenas leilões em rascunho podem ser editados.',
            ], 422);
        }

        $data = $request->validated();

        $auction->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starting_price' => $data['starting_price'],
            'current_price' => $data['starting_price'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['status'] ?? $auction->status,
        ]);

        return response()->json([
            'message' => 'Leilão atualizado com sucesso.',
            'auction' => $auction->fresh()->load(['creator:id,name,email']),
        ]);
    }

    public function destroy(Request $request, Auction $auction): JsonResponse
    {
        if ($auction->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir este leilão.',
            ], 403);
        }

        if ($auction->status !== 'draft') {
            return response()->json([
                'message' => 'Apenas leilões em rascunho podem ser excluídos.',
            ], 422);
        }

        $auction->delete();

        return response()->json([
            'message' => 'Leilão excluído com sucesso.',
        ]);
    }
}