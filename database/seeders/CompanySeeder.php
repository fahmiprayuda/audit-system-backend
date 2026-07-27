<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {

        $fks = Company::create([
            "code" => "FFS",
            "name" => "FKS Food Sejahtera",
        ]);

        $fks->departments()->createMany([

        [
            "code" => "FIN",
            "name" => "Finance & Accounting",
        ],

        [
            "code" => "HR",
            "name" => "HRGA",
        ],

        [
            "code" => "IT",
            "name" => "IT Department",
        ],

        [
            "code" => "MFG",
            "name" => "Manufacturing",
        ],

        [
            "code" => "PROC",
            "name" => "Procurement",
        ],

        [
            "code" => "SCM",
            "name" => "Supply Chain",
        ],

        [
            "code" => "RND",
            "name" => "Research & Development",
        ],

        [
            "code" => "QA",
            "name" => "Quality",
        ],

        [
            "code" => "TM",
            "name" => "Trade Marketing",
        ],

        [
            "code" => "MKT",
            "name" => "Marketing",
        ],

        [
            "code" => "OE",
            "name" => "Operational Excellence",
        ],

    ]);

    }
}