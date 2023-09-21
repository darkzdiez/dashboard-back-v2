<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Support\Facades\Cache;

class JobsController extends Controller {
    public function all() {
        $jobs = Cache::rememberForever('jobs.' . auth()->user()->id, function () {
            return DB::table('index_jobs')
                ->where('user_id', auth()->user()->id)
                ->orderByDesc('id')
                ->get();
        });
        return response()->json($jobs);
    }
}