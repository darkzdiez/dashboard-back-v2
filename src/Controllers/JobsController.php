<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use AporteWeb\Dashboard\Models\IndexJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use function response;
use function request;
use function auth;
use function activity;

class JobsController extends Controller {
    public function topBar() {
        $userId = Auth::id();

        $jobs = Cache::rememberForever('jobs.' . $userId, function () use ($userId) {
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
                    DB::raw('DATE_FORMAT(started_at, "%d/%m/%Y %h:%i:%s %p") as started_at'),
                    DB::raw('DATE_FORMAT(finished_at, "%d/%m/%Y %h:%i:%s %p") as finished_at'),
                    // duration
                    DB::raw('TIMESTAMPDIFF(SECOND, created_at, updated_at) as duration'),
                    // duration human
                    DB::raw('CONCAT(
                        FLOOR(TIMESTAMPDIFF(SECOND, created_at, updated_at) / 3600), "h ",
                        FLOOR((TIMESTAMPDIFF(SECOND, created_at, updated_at) % 3600) / 60), "m ",
                        FLOOR((TIMESTAMPDIFF(SECOND, created_at, updated_at) % 3600) % 60), "s"
                    ) as duration_human')
                )
                ->where('user_id', $userId)
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
            'started_at',
            'finished_at',
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

    public function callback($uuid) {
        $job = IndexJob::where('uuid', $uuid)->first();
        if ($job) {
            return response()->json(json_decode($job->callback));
        }
        return response()->json(['error' => 'Job not found'], 404);
    }

    public function queries($uuid) {
        $job = IndexJob::where('uuid', $uuid)->first();
        if ($job) {
            return response()->json($job->queries);
        }
        return response()->json(['error' => 'Job not found'], 404);
    }

    public function clearHistory(Request $request, ?string $scope = null): JsonResponse {
        $scope = $scope ?? $request->get('scope', 'all');
        $actor = Auth::user();

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = IndexJob::query();
        $shouldClearQueue = in_array($scope, ['queue', 'all'], true);

        switch ($scope) {
            case 'queue':
                $query->whereIn('status', ['waiting']);
                break;
            case 'completed':
                $query->whereIn('status', ['finish', 'failed']);
                break;
            case 'all':
            default:
                $scope = 'all';
                break;
        }

        $deletedIndexJobs = $query->delete();
        $deletedQueueJobs = 0;

        if ($shouldClearQueue) {
            $deletedQueueJobs = DB::table('jobs')->delete();
        }

        if (Auth::check()) {
            Cache::forget('jobs.' . Auth::id());
        }

        if ($actor) {
            $description = "El usuario {$actor->name} limpió el historial de jobs ({$scope})";

            activity()
                ->causedBy($actor)
                ->onLogName('index_jobs')
                ->forEvent('clear-history')
                ->withProperties([
                    'scope' => $scope,
                    'deleted_index_jobs' => $deletedIndexJobs,
                    'deleted_queue_jobs' => $deletedQueueJobs,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log($description);
        }

        return response()->json([
            'scope' => $scope,
            'deleted_index_jobs' => $deletedIndexJobs,
            'deleted_queue_jobs' => $deletedQueueJobs,
        ]);
    }
}