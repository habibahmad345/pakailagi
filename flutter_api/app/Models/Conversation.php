<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['item_id', 'buyer_id', 'seller_id'];

    public function item()
    {
        return $this->belongsTo(Item::class)->select('id', 'title', 'status', 'type', 'price');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->select('id', 'name');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->select('id', 'name');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->buyer_id === $userId || $this->seller_id === $userId;
    }
}