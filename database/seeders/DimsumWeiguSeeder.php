<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\PaymentSetting;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DimsumWeiguSeeder extends Seeder
{
    public function run(): void
    {
        $this->setting('store_name', 'Dimsum Weigu', 'Nama usaha');
        $this->setting('store_address', 'Bekasi', 'Alamat usaha');
        $this->setting('store_phone', '083129701342', 'Nomor kontak usaha');
        $this->setting('store_email', 'faridindrawan@gmail.com', 'Email usaha');
        $this->setting('store_business_type', 'food', 'Jenis usaha');
        $this->setting('app_setup_completed', true, 'Profil dan data awal diatur melalui seeder');

        foreach (['Dimsum', 'Lumpia', 'Chiquro', 'Paket Besar'] as $name) {
            Category::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
        }

        foreach ([
            ['code' => 'PCS', 'name' => 'Buah', 'symbol' => 'pcs'],
            ['code' => 'BOX', 'name' => 'Kotak', 'symbol' => 'kotak'],
            ['code' => 'KARTON', 'name' => 'Karton', 'symbol' => 'karton'],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg'],
            ['code' => 'LITER', 'name' => 'Liter', 'symbol' => 'L'],
            ['code' => 'METER', 'name' => 'Meter', 'symbol' => 'm'],
            ['code' => 'PAK', 'name' => 'Paket', 'symbol' => 'paket'],
            ['code' => 'DUS', 'name' => 'Dus', 'symbol' => 'dus'],
            ['code' => 'GRAM', 'name' => 'Gram', 'symbol' => 'g'],
            ['code' => 'ML', 'name' => 'Mililiter', 'symbol' => 'ml'],
        ] as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }

        foreach (['Protein', 'Kulit dan Tepung', 'Saus', 'Produk Susu', 'Bumbu', 'Kemasan', 'Sayuran'] as $name) {
            IngredientCategory::firstOrCreate(['name' => $name]);
        }

        foreach ([
            ['from' => 'KG', 'to' => 'GRAM', 'factor' => 1000],
            ['from' => 'GRAM', 'to' => 'KG', 'factor' => 0.001],
            ['from' => 'LITER', 'to' => 'ML', 'factor' => 1000],
            ['from' => 'ML', 'to' => 'LITER', 'factor' => 0.001],
        ] as $conversion) {
            UnitConversion::firstOrCreate([
                'from_unit_id' => Unit::where('code', $conversion['from'])->value('id'),
                'to_unit_id' => Unit::where('code', $conversion['to'])->value('id'),
            ], ['factor' => $conversion['factor']]);
        }

        foreach ([['cash', 'Tunai', null, false], ['qris', 'QRIS', 'qris', true], ['gofood', 'GoFood / Online', 'gofood', true]] as [$code, $name, $provider, $confirm]) {
            PaymentMethod::firstOrCreate(['code' => $code], ['name' => $name, 'provider' => $provider, 'is_enabled' => true, 'requires_confirmation' => $confirm]);
        }
        foreach (['dine_in_enabled' => true, 'dine_in_self_order_enabled' => true, 'dine_in_pay_online_enabled' => false, 'dine_in_online_payment' => false, 'default_gateway' => 'cash', 'midtrans_enabled' => false, 'xendit_enabled' => false, 'allow_public_registration' => false, 'default_tax_rate' => 0, 'tax_mode' => 'exclusive'] as $key => $value) {
            $this->setting($key, $value);
        }

        $branches = [
            [
                'code' => 'GAL-BP',
                'name' => 'Galaxy — Depan SPBU BP',
                'address' => 'Di depan SPBU BP, Galaxy',
                'map_url' => 'https://maps.app.goo.gl/u9Gg5ufsxPXNCmwd9',
                'opening_hours' => [['days' => 'Setiap hari', 'time' => '14.00–00.00']],
            ],
            [
                'code' => 'GAL-HER',
                'name' => 'Galaxy — Samping RS Hermina',
                'address' => 'Di samping RS Hermina, Galaxy',
                'map_url' => 'https://maps.app.goo.gl/xyGKHdRHhcAeBMkA7',
                'opening_hours' => [['days' => 'Setiap hari', 'time' => '08.00–18.00']],
            ],
            [
                'code' => 'PEK-JAYA',
                'name' => 'Pekayon Jaya',
                'address' => 'Pekayon Jaya',
                'map_url' => 'https://maps.app.goo.gl/vBkAyD4WVhMoQUvm6',
                'opening_hours' => [
                    ['days' => 'Senin–Sabtu', 'time' => '14.00–22.00'],
                    ['days' => 'Minggu', 'time' => '06.00–16.00'],
                ],
            ],
            [
                'code' => 'JL-RAYA-PEK',
                'name' => 'Jalan Raya Pekayon — Pakuwon Mall',
                'address' => 'Di samping Pakuwon Mall Bekasi, Jalan Raya Pekayon',
                'map_url' => 'https://maps.app.goo.gl/6efBgdCTcRjrqytH7',
                'opening_hours' => [['days' => 'Setiap hari', 'time' => '14.30–22.15']],
            ],
        ];

        foreach ($branches as $branch) {
            Outlet::firstOrCreate(
                ['code' => $branch['code']],
                [
                    'slug' => strtolower($branch['code']),
                    'name' => $branch['name'],
                    'is_active' => true,
                    'is_sales_enabled' => true,
                    'address' => $branch['address'],
                    'map_url' => $branch['map_url'],
                    'opening_hours' => $branch['opening_hours'],
                    'phone' => '083129701342',
                    'email' => 'faridindrawan@gmail.com',
                ],
            );

        }

        $legacyCodes = ['GAL-BP' => 'WH-GAL-BP', 'GAL-HER' => 'WH-GAL-HER', 'PEK-JAYA' => 'WH-PEK-JAYA', 'JL-RAYA-PEK' => 'WH-RAYA-PEK'];
        foreach ($legacyCodes as $outletCode => $warehouseCode) {
            Warehouse::firstOrCreate(
                ['code' => $warehouseCode],
                ['outlet_id' => Outlet::where('code', $outletCode)->value('id'), 'name' => 'Stok Outlet '.$outletCode, 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true],
            );
        }
        Warehouse::firstOrCreate(
            ['code' => 'PUSAT'],
            [
                'outlet_id' => null,
                'name' => 'Gudang Bersama Dimsum Weigu',
                'type' => 'central',
                'is_active' => true,
                'is_sales_enabled' => false,
            ],
        );
        foreach (['midtrans', 'xendit'] as $provider) {
            PaymentSetting::firstOrCreate(['scope_key' => 'global', 'provider' => $provider], ['mode' => 'sandbox', 'is_enabled' => false]);
        }
        foreach (Outlet::whereIn('code', array_keys($legacyCodes))->get() as $outlet) {
            foreach (['Sewa', 'Lapak', 'Energi/Gas', 'Gaji Karyawan', 'Pembelanjaan Bahan', 'Pembelanjaan Operasional Outlet'] as $name) {
                ExpenseCategory::firstOrCreate(['outlet_id' => $outlet->id, 'name' => $name]);
            }
        }
    }

    private function setting(string $key, mixed $value, string $description = ''): void
    {
        Setting::firstOrCreate(['scope_key' => 'global', 'key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'dine_in') ? 'dine-in' : 'general']);
    }
}
