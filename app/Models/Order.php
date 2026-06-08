<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'sales_event_id',
        'order_no',
        'status',
        'price',
        'quantity',
        'ordered_at',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function salesEvent()
    {
        return $this->belongsTo(SalesEvent::class);
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }
}
