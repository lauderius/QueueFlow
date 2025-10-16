<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index()
    {
        $rows = DB::table('queues')
            ->select('id','name','group_name','avg_service_sec','last_number','active','created_at','updated_at')
            ->where('active', 1)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();
        return response()->json(['queues' => $rows]);
    }
}
