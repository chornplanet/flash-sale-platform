<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogDatabaseQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'development'])) {
            return $next($request);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $next($request);

        $queries = DB::getQueryLog();

        Log::debug('Database query summary', [
            'path' => $request->path(),
            'method' => $request->method(),
            'query_count' => count($queries),
        ]);

        DB::disableQueryLog();

        return $response;
    }
}
