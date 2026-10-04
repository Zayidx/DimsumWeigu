<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InitialBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $units = [];
        foreach ([['pieces', 'pcs', 'count'], ['box', 'box', 'count'], ['gram', 'g', 'mass'], ['kilogram', 'kg', 'mass'], ['milliliter', 'ml', 'volume'], ['liter', 'L', 'volume']] as [$name, $symbol, $dimension]) {
            $units[$symbol] = Unit::query()->updateOrCreate(['symbol' => $symbol], ['name' => $name, 'dimension' => $dimension, 'is_active' => true]);
        }
        foreach ([['kg', 'g', 1000], ['g', 'kg', 0.001], ['L', 'ml', 1000], ['ml', 'L', 0.001]] as [$from, $to, $factor]) {
            UnitConversion::query()->updateOrCreate(['from_unit_id' => $units[$from]->id, 'to_unit_id' => $units[$to]->id], ['factor' => $factor]);
        }

        Warehouse::query()->updateOrCreate(
            ['code' => 'PUSAT'],
            ['outlet_id' => null, 'name' => 'Gudang Pusat', 'type' => 'central', 'is_active' => true, 'is_sales_enabled' => false],
        );

        $branches = [
            ['code' => 'GAL1', 'name' => 'Galaxy', 'address' => 'Di depan SPBU BP, Galaxy', 'map_url' => 'https://maps.app.goo.gl/u9Gg5ufsxPXNCmwd9', 'hours' => [['days' => 'Setiap hari', 'time' => '14.00–00.00']]],
            ['code' => 'GAL2', 'name' => 'Galaxy', 'address' => 'Di samping RS Hermina, Galaxy', 'map_url' => 'https://maps.app.goo.gl/xyGKHdRHhcAeBMkA7', 'hours' => [['days' => 'Setiap hari', 'time' => '08.00–18.00']]],
            ['code' => 'PEK1', 'name' => 'Pekayon Jaya', 'address' => 'Pekayon Jaya', 'map_url' => 'https://maps.app.goo.gl/vBkAyD4WVhMoQUvm6', 'hours' => [['days' => 'Senin–Sabtu', 'time' => '14.00–22.00'], ['days' => 'Minggu', 'time' => '06.00–16.00']]],
            ['code' => 'PEK2', 'name' => 'Jalan Raya Pekayon', 'address' => 'Di samping Pakuwon Mall Bekasi', 'map_url' => 'https://maps.app.goo.gl/6efBgdCTcRjrqytH7', 'hours' => [['days' => 'Setiap hari', 'time' => '14.30–22.15']]],
        ];

        foreach ($branches as $branch) {
            $outlet = Outlet::query()->updateOrCreate(
                ['code' => $branch['code']],
                [
                    'slug' => strtolower($branch['code']), 'name' => $branch['name'],
                    'phone' => '0895343256336', 'email' => 'Weiguowner@gmail.com',
                    'address' => $branch['address'], 'city' => 'Bekasi', 'province' => 'Jawa Barat',
                    'map_url' => $branch['map_url'], 'opening_hours' => $branch['hours'],
                    'is_active' => true, 'is_sales_enabled' => true,
                ],
            );

            Warehouse::query()->updateOrCreate(
                ['code' => 'WH-'.$branch['code']],
                ['outlet_id' => $outlet->id, 'name' => 'Gudang '.$branch['name'], 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true],
            );
        }

        foreach ([
            ['store_name', 'Weigu Dimsum & Gyoza', 'general'],
            ['store_description', 'Dimsum hangat, dibuat dengan bahan pilihan dan penuh perhatian.', 'general'],
            ['store_phone', '0895343256336', 'contact'],
            ['store_email', 'Weiguowner@gmail.com', 'contact'],
            ['default_tax_rate', 0, 'tax'], ['tax_mode', 'exclusive', 'tax'],
            ['allow_public_registration', false, 'security'], ['dine_in_online_payment', false, 'dine-in'],
        ] as [$key, $value, $group]) {
            Setting::write($key, $value, $group);
        }

        foreach ([
            ['cash', 'Tunai', false], ['bank-transfer', 'Transfer bank', true],
            ['qris', 'QRIS', true], ['pay-later', 'Bayar nanti', true],
        ] as [$code, $name, $requiresConfirmation]) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'provider' => $code === 'qris' ? 'qris' : null, 'is_enabled' => $code === 'cash', 'requires_confirmation' => $requiresConfirmation],
            );
        }
    }
}
