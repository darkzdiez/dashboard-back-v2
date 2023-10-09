<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Support\Facades\Cache;

class JobsController extends Controller {
    public function topBar() {
        $jobs = Cache::rememberForever('jobs.' . auth()->user()->id, function () {
            return DB::table('index_jobs')
                ->select(
                    'uuid',
                    'queue',
                    'title',
                    'description',
                    'group',
                    'icon',
                    'level',
                    'status'
                )
                ->where('user_id', auth()->user()->id)
                ->orderByDesc('id')
                ->paginate(7);
        });
        return response()->json($jobs);
    }
    public function all() {
        $jobs = Cache::rememberForever('jobs.' . auth()->user()->id, function () {
            return DB::table('index_jobs')
                ->where('user_id', auth()->user()->id)
                ->orderByDesc('id')
                ->paginate(7);
        });
        return response()->json($jobs);
    }
}