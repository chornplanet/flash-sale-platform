<?php

namespace App\Http\Controllers\Api;

use App\Models\SalesEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesEventController extends Controller
{
    public function index(Request $request)
    {
        return SalesEvent::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latest('starts_at')
            ->paginate($request->integer('per_page', 20));
    }
}
