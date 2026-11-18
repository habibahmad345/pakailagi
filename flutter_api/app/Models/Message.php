<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    // Tabel messages hanya punya created_at (diisi otomatis oleh database)
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];
}