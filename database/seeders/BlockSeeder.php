<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (\Illuminate\Support\Facades\DB::table('blocks')->count() === 0) {
            \Illuminate\Support\Facades\DB::table('blocks')->insert([
                ['id' => 13,   'name' => 'B.K.Pora',           'slug' => 'b-k-pora',            'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 14,   'name' => 'Badgam',              'slug' => 'badgam',              'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 15,   'name' => 'Beerwah',             'slug' => 'beerwah',             'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 16,   'name' => 'Chadoora',            'slug' => 'chadoora',            'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 17,   'name' => 'Khag',                'slug' => 'khag',                'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 18,   'name' => 'Khan-Sahib',          'slug' => 'khan-sahib',          'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 19,   'name' => 'Nagam',               'slug' => 'nagam',               'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 20,   'name' => 'Narbal',              'slug' => 'narbal',              'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6915, 'name' => 'Parnewa',             'slug' => 'parnewa',             'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6916, 'name' => 'Sukhnag Hard Panzoo', 'slug' => 'sukhnag-hard-panzoo', 'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6917, 'name' => 'Waterhail',           'slug' => 'waterhail',           'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6918, 'name' => 'Pakherpora',          'slug' => 'pakherpora',          'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6919, 'name' => 'Charisharief',        'slug' => 'charisharief',        'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6920, 'name' => 'Surasyar',            'slug' => 'surasyar',            'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6921, 'name' => 'Soibugh',             'slug' => 'soibugh',             'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6922, 'name' => 'Rathsun',             'slug' => 'rathsun',             'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6923, 'name' => 'S K Pora',            'slug' => 's-k-pora',            'district_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
