<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'stock_count',
        'reserved_count',
        'price',
        'is_active',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function salesEvents()
    {
        return $this->belongsToMany(SalesEvent::class)
            ->withPivot(['event_stock_limit', 'event_price'])
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
