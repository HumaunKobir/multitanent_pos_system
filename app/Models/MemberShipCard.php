<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberShipCard extends Model
{
    protected $table = 'member_ship_cards';

    protected $fillable = ['name', 'image', 'status'];
}
