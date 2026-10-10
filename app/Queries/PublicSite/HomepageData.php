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

        $settings = Setting::query()->where('scope_key', 'global')->whereIn('key', [
            'store_name', 'store_phone', 'store_email', 'store_logo', 'store_description', 'homepage_content',
        ])->get()->keyBy('key');
        $value = function (string $key, mixed $default = null) use ($settings): mixed {
            $setting = $settings->get($key);
            $stored = $setting?->value;
            if ($setting?->is_encrypted && is_array($stored) && isset($stored['ciphertext'])) {
                return decrypt($stored['ciphertext']);
            }

            return $stored ?? $default;
        };

        $name = $value('store_name', 'Weigu Dimsum & Gyoza');
        $phone = $value('store_phone', '0895343256336');
        $email = $value('store_email', 'Weiguowner@gmail.com');
        $logo = $value('store_logo');
        $savedHomepage = $value('homepage_content', []);
        $homepageDefaults = [
            'hero_eyebrow' => 'Dimsum · lumpia · Chiquro',
            'hero_title' => 'Pilih menu Weigu untuk hari ini.',
            'hero_caption' => 'Lihat pilihan menu dan harga, lalu temukan cabang Weigu terdekat.',
            'hero_image' => null,
            'order_url' => null,
            'order_label' => 'Pesan sekarang',
            'story_eyebrow' => 'Pilihan di Weigu',
            'story_title' => 'Mulai dari menu yang kamu suka.',
            'story_body' => 'Pilih dimsum kukus dengan beberapa pilihan porsi, lumpia, Chiquro, atau paket besar.',
            'story_second' => 'Harga tercantum pada setiap menu agar mudah dibandingkan sebelum memesan.',
            'menu_title' => 'Lihat menu & harga.',
            'menu_caption' => 'Pilih kategori untuk melihat pilihan yang tersedia.',
            'outlet_title' => 'Temukan outlet terdekat.',
            'outlet_caption' => 'Lihat alamat, jam buka, dan petunjuk peta setiap cabang.',
            'menu_images' => [],
        ];
        $homepage = is_array($savedHomepage) ? array_replace($homepageDefaults, $savedHomepage) : $homepageDefaults;
        $homepage['menu_images'] = is_array($homepage['menu_images']) ? $homepage['menu_images'] : [];

        return [
            'business' => [
                'name' => is_string($name) && $name !== '' ? $name : 'Weigu Dimsum & Gyoza',
                'description' => (string) $value('store_description', ''),
                'logo' => is_string($logo) && $logo !== '' ? asset('storage/'.$logo) : null,
            ],
            'outlets' => $outlets,
            'phone' => (string) $phone,
            'email' => (string) $email,
            'homepage' => $homepage,
        ];
    }
}
