<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use AporteWeb\Dashboard\Models\IndexJob;
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
                    'status',
                    // format created_at and updated_at 30/11/2023 10:48:00 PM
                    DB::raw('DATE_FORMAT(created_at, "%d/%m/%Y %h:%i:%s %p") as created_at'),
                    DB::raw('DATE_FORMAT(updated_at, "%d/%m/%Y %h:%i:%s %p") as updated_at'),
                    // duration
                    DB::raw('TIMESTAMPDIFF(SECOND, created_at, updated_at) as duration'),
                    // duration human
                    DB::raw('CONCAT(
                        FLOOR(TIMESTAMPDIFF(SECOND, created_at, updated_at) / 3600), "h ",
                        FLOOR((TIMESTAMPDIFF(SECOND, created_at, updated_at) % 3600) / 60), "m ",
                        FLOOR((TIMESTAMPDIFF(SECOND, created_at, updated_at) % 3600) % 60), "s"
                    ) as duration_human')
                )
                ->where('user_id', auth()->user()->id)
                ->orderByDesc('id')
                ->paginate(7);
        });
        return response()->json($jobs);
    }
    public function all() {
        $data = IndexJob::select(
            'uuid',
            'queue',
            'title',
            'description',
            'group',
            'icon',
            'level',
            'status',
            'created_at',
            'user_id',
            'time_execution',
            'memory_usage',
            'queries_time',
            'queries_count'
        )
            ->with([ 'user' => function ($query) {
                $query->select('id', 'name');
            }])
            ->orderByDesc('id');

        if ( request()->has('filters') && is_array(request()->filters) ) {
            foreach (request()->filters as $key => $value) {
                $data->where($key, 'like', '%'.$value.'%');
            }
        }
        if ( request()->has('trash') && request()->trash == 1 ) {
            $data->onlyTrashed();
        }
    
        return $data->paginate(30);
    }
}