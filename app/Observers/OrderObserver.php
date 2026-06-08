<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->flushDashboardCache();
    }

    public function updated(Order $order): void
    {
        $this->flushDashboardCache();
    }

    public function deleted(Order $order): void
    {
        $this->flushDashboardCache();
    }

    public function restored(Order $order): void
    {
        $this->flushDashboardCache();
    }

    public function forceDeleted(Order $order): void
    {
        $this->flushDashboardCache();
    }

    private function flushDashboardCache(): void
    {
        Cache::tags(['dashboard'])->flush();
    }
}
