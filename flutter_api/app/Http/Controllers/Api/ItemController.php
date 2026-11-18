<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    // Daftar + pencarian (kata kunci, kategori, tipe, kota, radius lokasi)
    public function index(Request $request)
    {
        $request->validate([
            'lat'    => 'nullable|numeric|between:-90,90',
            'lng'    => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:500',
        ]);

        $query = Item::with(['images', 'category:id,name,slug', 'seller'])
            ->where('items.status', 'tersedia');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (float) ($request->radius ?? 20);

            $query->select('items.*')
                ->selectRaw(
                    '(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(latitude))))) AS distance_km',
                    [$lat, $lng, $lat]
                )
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->having('distance_km', '<=', $radius)
                ->orderBy('distance_km');
        } else {
            $query->latest();
        }

        return $query->paginate(20);
    }

    public function show(Item $item)
    {
        return $item->load(['images', 'category:id,name,slug', 'seller']);
    }

    // Barang milik penjual yang sedang login
    public function mine(Request $request)
    {
        return Item::with(['images', 'category:id,name,slug'])
            ->where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string',
            'type'        => 'required|in:jual,gratis',
            'price'       => 'nullable|numeric|min:0',
            'kondisi'     => 'nullable|in:seperti_baru,baik,cukup,perlu_perbaikan',
            'city'        => 'nullable|string|max:100',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'images'      => 'nullable|array|max:5',
            'images.*'    => 'image|max:2048',
        ]);

        $user = $request->user();

        if ($data['type'] === 'gratis') {
            $data['price'] = 0;
        } else {
            $data['price'] = $data['price'] ?? 0;
        }

        // Kalau lokasi barang tidak diisi, pakai lokasi penjual
        $data['city']      = $data['city']      ?? $user->city;
        $data['latitude']  = $data['latitude']  ?? $user->latitude;
        $data['longitude'] = $data['longitude'] ?? $user->longitude;
        $data['seller_id'] = $user->id;

        $item = Item::create(collect($data)->except('images')->all());

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('items', 'public');
                ItemImage::create([
                    'item_id'    => $item->id,
                    'image_url'  => $path,
                    'sort_order' => $i,
                ]);
            }
        }

        return response()->json(
            $item->load(['images', 'category:id,name,slug', 'seller']),
            201
        );
    }

    public function update(Request $request, Item $item)
    {
        if ($item->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Bukan barang Anda'], 403);
        }

        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'title'       => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'type'        => 'sometimes|in:jual,gratis',
            'price'       => 'nullable|numeric|min:0',
            'kondisi'     => 'sometimes|in:seperti_baru,baik,cukup,perlu_perbaikan',
            'status'      => 'sometimes|in:tersedia,dipesan,selesai',
            'city'        => 'nullable|string|max:100',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ]);

        $type = $data['type'] ?? $item->type;
        if ($type === 'gratis') {
            $data['price'] = 0;
        }

        $item->update($data);

        return $item->load(['images', 'category:id,name,slug', 'seller']);
    }

    public function destroy(Request $request, Item $item)
    {
        if ($item->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Bukan barang Anda'], 403);
        }

        foreach ($item->images as $img) {
            Storage::disk('public')->delete($img->image_url);
        }

        $item->delete();

        return response()->json(['message' => 'Barang dihapus']);
    }
}