<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $table = 'product_categories';
    public $timestamps = false;

    protected $fillable = ['alias', 'root'];

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'root');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'root');
    }
}
