<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NigeriaGeographySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $zones = [
            ['code' => 'NC', 'name' => 'North Central', 'sort_order' => 1],
            ['code' => 'NE', 'name' => 'North East',    'sort_order' => 2],
            ['code' => 'NW', 'name' => 'North West',    'sort_order' => 3],
            ['code' => 'SE', 'name' => 'South East',    'sort_order' => 4],
            ['code' => 'SS', 'name' => 'South South',   'sort_order' => 5],
            ['code' => 'SW', 'name' => 'South West',    'sort_order' => 6],
        ];

        foreach ($zones as &$zone) {
            $zone['slug'] = Str::slug($zone['name']);
            $zone['is_active'] = true;
            $zone['created_at'] = $now;
            $zone['updated_at'] = $now;
        }
        unset($zone);

        DB::table('geopolitical_zones')->upsert(
            $zones,
            ['code'],
            ['name', 'slug', 'sort_order', 'is_active', 'updated_at']
        );

        $zoneIds = DB::table('geopolitical_zones')
            ->pluck('id', 'code');

        $states = [
            ['NC', 'BE', 'Benue', false],
            ['NC', 'FC', 'Federal Capital Territory', true],
            ['NC', 'KO', 'Kogi', false],
            ['NC', 'KW', 'Kwara', false],
            ['NC', 'NA', 'Nasarawa', false],
            ['NC', 'NI', 'Niger', false],
            ['NC', 'PL', 'Plateau', false],

            ['NE', 'AD', 'Adamawa', false],
            ['NE', 'BA', 'Bauchi', false],
            ['NE', 'BO', 'Borno', false],
            ['NE', 'GO', 'Gombe', false],
            ['NE', 'TA', 'Taraba', false],
            ['NE', 'YO', 'Yobe', false],

            ['NW', 'JI', 'Jigawa', false],
            ['NW', 'KD', 'Kaduna', false],
            ['NW', 'KN', 'Kano', false],
            ['NW', 'KT', 'Katsina', false],
            ['NW', 'KE', 'Kebbi', false],
            ['NW', 'SO', 'Sokoto', false],
            ['NW', 'ZA', 'Zamfara', false],

            ['SE', 'AB', 'Abia', false],
            ['SE', 'AN', 'Anambra', false],
            ['SE', 'EB', 'Ebonyi', false],
            ['SE', 'EN', 'Enugu', false],
            ['SE', 'IM', 'Imo', false],

            ['SS', 'AK', 'Akwa Ibom', false],
            ['SS', 'BY', 'Bayelsa', false],
            ['SS', 'CR', 'Cross River', false],
            ['SS', 'DE', 'Delta', false],
            ['SS', 'ED', 'Edo', false],
            ['SS', 'RI', 'Rivers', false],

            ['SW', 'EK', 'Ekiti', false],
            ['SW', 'LA', 'Lagos', false],
            ['SW', 'OG', 'Ogun', false],
            ['SW', 'ON', 'Ondo', false],
            ['SW', 'OS', 'Osun', false],
            ['SW', 'OY', 'Oyo', false],
        ];

        $rows = [];

        foreach ($states as [$zoneCode, $code, $name, $isFct]) {
            $rows[] = [
                'geopolitical_zone_id' => $zoneIds[$zoneCode],
                'name' => $name,
                'slug' => Str::slug($name),
                'code' => $code,
                'is_fct' => $isFct,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('states')->upsert(
            $rows,
            ['code'],
            [
                'geopolitical_zone_id',
                'name',
                'slug',
                'is_fct',
                'is_active',
                'updated_at',
            ]
        );
    }
}
