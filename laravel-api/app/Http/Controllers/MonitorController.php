<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    public function show(int $queue)
    {
        $now = DB::table('tickets')
            ->select('number','ext_id')
            ->where('queue_id', $queue)
            ->where('status', 'serving')
            ->orderBy('started_at')
            ->first();
        $next = DB::table('tickets')
            ->select('number','ext_id')
            ->where('queue_id', $queue)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(5)
            ->get();
        return response()->json(['now' => $now ?: null, 'next' => $next]);
    }
}
