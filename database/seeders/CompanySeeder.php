<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {

        Company::insert([

            [
                'code' => 'FFS',
                'name' => 'Fks Food Sejahtera'
            ],

            [
                'code' => 'TPS',
                'name' => 'Tiga Pilar Sejahtera'
            ],

            [
                'code' => 'PTP',
                'name' => 'Putra Taro Paloma'
            ],

            [
                'code' => 'SPJ',
                'name' => 'Subafood Pangan Jaya'
            ]

        ]);

    }
}