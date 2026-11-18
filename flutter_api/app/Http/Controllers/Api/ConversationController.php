<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Item;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // Daftar percakapan milik saya (sebagai pembeli atau penjual)
    public function index(Request $request)
    {
        $uid = $request->user()->id;

        return Conversation::with(['item', 'buyer', 'seller', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($uid) {
                $q->where('is_read', false)->where('sender_id', '!=', $uid);
            }])
            ->where(function ($q) use ($uid) {
                $q->where('buyer_id', $uid)->orWhere('seller_id', $uid);
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    // Mulai chat dengan penjual suatu barang (kalau sudah ada, dikembalikan yang lama)
    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        $user = $request->user();
        $item = Item::findOrFail($data['item_id']);

        if ($item->seller_id === $user->id) {
            return response()->json(['message' => 'Tidak bisa chat dengan barang sendiri'], 422);
        }

        $conversation = Conversation::firstOrCreate(
            ['item_id' => $item->id, 'buyer_id' => $user->id],
            ['seller_id' => $item->seller_id]
        );

        return response()->json(
            $conversation->load(['item', 'buyer', 'seller']),
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }
}