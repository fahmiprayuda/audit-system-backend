<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingSequence extends Model
{
    protected $fillable = [
        'company_code',
        'year',
        'last_number'
    ];
}