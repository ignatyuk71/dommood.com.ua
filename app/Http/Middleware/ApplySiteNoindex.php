<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySiteNoindex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('seo.noindex_site')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
