<?php

namespace App\Queries\PublicSite;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class HomepageData
{
    public function get(): array
    {
        $products = Schema::hasTable('products')
            ? Product::query()
                ->whereNotNull('image')
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get(['id', 'title', 'description', 'sell_price', 'image'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->title,
                    'description' => $product->description,
                    'price' => $product->sell_price,
                    'image' => $product->image,
                ])
            : collect();

        $logo = Setting::get('store_logo');
        $outlets = Outlet::query()
            ->where('is_active', true)
            ->where('is_sales_enabled', true)
            ->orderBy('id')
            ->get(['id', 'name', 'address', 'map_url', 'opening_hours'])
            ->values()
            ->map(fn (Outlet $outlet, int $index) => [
                'id' => $outlet->id,
                'name' => 'Cabang '.($index + 1).' · '.$outlet->name,
                'address' => $outlet->address,
                'map' => $outlet->map_url,
                'hours' => $outlet->opening_hours ?? [],
            ]);

        return [
            'business' => [
                'name' => Setting::get('store_name', 'Dimsum'),
                'description' => Setting::get('store_description'),
                'phone' => Setting::get('store_phone'),
                'email' => Setting::get('store_email'),
                'logo' => $logo ? asset('storage/'.$logo) : null,
            ],
            'products' => $products,
            'outlets' => $outlets,
        ];
    }
}
