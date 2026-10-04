<?php

namespace App\Queries\PublicSite;

use App\Models\Outlet;
use App\Models\Setting;

class HomepageData
{
    public function get(): array
    {
        $outlets = Outlet::query()->where('is_active', true)->where('is_sales_enabled', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'address', 'map_url', 'opening_hours'])
            ->values()->map(fn (Outlet $outlet, int $index) => [
                'id' => $outlet->id,
                'name' => 'Cabang '.($index + 1).' · '.$outlet->name,
                'address' => $outlet->address,
                'map' => $outlet->map_url,
                'hours' => $outlet->opening_hours ?? [],
            ]);

        $name = Setting::value('store_name', 'Weigu Dimsum & Gyoza');
        $phone = Setting::value('store_phone', '0895343256336');
        $email = Setting::value('store_email', 'Weiguowner@gmail.com');
        $logo = Setting::value('store_logo');

        return [
            'business' => [
                'name' => is_string($name) && $name !== '' ? $name : 'Weigu Dimsum & Gyoza',
                'description' => (string) Setting::value('store_description', ''),
                'logo' => is_string($logo) && $logo !== '' ? asset('storage/'.$logo) : null,
            ],
            'outlets' => $outlets,
            'phone' => (string) $phone,
            'email' => (string) $email,
        ];
    }
}
