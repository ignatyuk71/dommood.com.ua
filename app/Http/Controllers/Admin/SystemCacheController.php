<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SystemCacheController extends Controller
{
    public function clear(Request $request): JsonResponse
    {
        Artisan::call('optimize:clear');
        Cache::flush();

        app(AdminActivityLogger::class)->log(
            $request,
            'system.cache_cleared',
            newValues: ['updated' => true],
            description: 'Менеджер очистив системний кеш',
        );

        return response()->json([
            'message' => 'Кеш очищено',
        ]);
    }
}
