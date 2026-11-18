<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'image_url', 'sort_order'];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->image_url);
    }
}