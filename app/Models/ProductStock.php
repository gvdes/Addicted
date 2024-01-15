<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $table = 'product_stock';
    public $timestamps = false;

    public function product(){
        return $this->belongsTo('App\Models\Product', '_product');
    }
    public function warehouse(){
        return $this->hasOne('App\Models\Warehouse','id','_warehouse');
    }

}
