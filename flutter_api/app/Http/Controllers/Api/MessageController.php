<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Ambil pesan. Untuk polling: kirim ?after_id=<id pesan terakhir yang sudah dimiliki>
    public function index(Request $request, Conversation $conversation)
    {
        $uid = $request->user()->id;

        if (!$conversation->hasParticipant($uid)) {
            return response()->json(['message' => 'Bukan percakapan Anda'], 403);
        }

        // Tandai pesan dari lawan bicara sebagai sudah dibaca
        $conversation->messages()
            ->where('sender_id', '!=', $uid)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $query = $conversation->messages()->orderBy('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->after_id);
        }

        return $query->get();
    }

    public function store(Request $request, Conversation $conversation)
    {
        $uid = $request->user()->id;

        if (!$conversation->hasParticipant($uid)) {
            return response()->json(['message' => 'Bukan percakapan Anda'], 403);
        }

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $uid,
            'body'            => $data['body'],
        ]);

        // Naikkan percakapan ke urutan teratas di daftar chat
        $conversation->touch();

        return response()->json($message->refresh(), 201);
    }
}