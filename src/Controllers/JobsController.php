<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use AporteWeb\Dashboard\Models\IndexJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Opis\Closure\SerializableClosure;
use ReflectionFunction;
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

    public function cancel(Request $request, string $uuid): JsonResponse {
        $indexJob = IndexJob::where('uuid', $uuid)->first();

        if (!$indexJob) {
            return response()->json(['message' => 'Job no encontrado.'], 404);
        }

        if ($indexJob->status !== 'waiting') {
            return response()->json([
                'message' => 'Solo se pueden cancelar jobs en espera.',
                'status' => $indexJob->status,
            ], 422);
        }

        $actor = Auth::user();
        $queueJob = $this->findQueueJobByIndexJob($indexJob);
        $deletedQueueJob = 0;

        if ($queueJob) {
            $deletedQueueJob = DB::table('jobs')->where('id', $queueJob->id)->delete();
        }

        $indexJob->status = 'cancelled';
        $indexJob->finished_at = now();
        $indexJob->updated_at = now();
        $indexJob->callback = json_encode([
            'status' => 'cancelled',
            'cancelled_by' => $actor ? $actor->only(['id', 'name']) : null,
            'cancelled_at' => now()->toIso8601String(),
            'deleted_queue_job' => (bool) $deletedQueueJob,
        ], JSON_PRETTY_PRINT);
        $indexJob->save();
        $indexJob->refresh();

        if ($indexJob->user_id) {
            Cache::forget('jobs.' . $indexJob->user_id);
        }

        if ($actor) {
            Cache::forget('jobs.' . $actor->id);
        }

        try {
            Storage::disk('local')->put(
                'dashboard-task/' . $indexJob->uuid . '.json',
                json_encode(['status' => 'cancelled'], JSON_PRETTY_PRINT)
            );
        } catch (\Throwable $exception) {
            // Ignorar errores de almacenamiento, no son críticos para la cancelación
        }

        if ($actor) {
            activity()
                ->causedBy($actor)
                ->onLogName('index_jobs')
                ->forEvent('cancel')
                ->withProperties([
                    'uuid' => $indexJob->uuid,
                    'queue' => $indexJob->queue,
                    'deleted_queue_job' => (bool) $deletedQueueJob,
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log("El usuario {$actor->name} canceló el job {$indexJob->title} ({$indexJob->uuid})");
        }

        return response()->json([
            'message' => 'Job cancelado correctamente.',
            'deleted_queue_job' => (bool) $deletedQueueJob,
            'job' => $indexJob,
        ]);
    }

    public function clearHistory(Request $request, ?string $scope = null): JsonResponse {
        $scope = $scope ?? $request->get('scope', 'all');
        $actor = Auth::user();

        $deletedIndexJobs = 0;
        $deletedQueueJobs = 0;

        if ($scope === 'all') {
            $tablesToTruncate = [
                'failed_jobs',
                'index_jobs',
                'jobs',
                'job_batches',
                'processed_jobs',
            ];
            foreach ($tablesToTruncate as $table) {
                try {
                    DB::table($table)->truncate();
                    Log::info("Truncated table {$table} successfully.");
                } catch (\Exception $e) {
                    Log::error("Error truncating table {$table}: " . $e->getMessage());
                }
            }
        } else {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = IndexJob::query();
            $shouldClearQueue = $scope === 'queue';

            switch ($scope) {
                case 'queue':
                    $query->whereIn('status', ['waiting']);
                    break;
                case 'completed':
                    $query->whereIn('status', ['finish', 'failed']);
                    break;
            }

            $deletedIndexJobs = $query->delete();

            if ($shouldClearQueue) {
                $deletedQueueJobs = DB::table('jobs')->delete();
            }
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

    private function findQueueJobByIndexJob(IndexJob $indexJob): ?object {
        $queueJobs = DB::table('jobs')
            ->where('queue', $indexJob->queue)
            ->orderBy('id')
            ->get();

        foreach ($queueJobs as $queueJob) {
            $payload = json_decode($queueJob->payload, true);

            if (!is_array($payload) || empty($payload['data']['command'])) {
                continue;
            }

            $command = $payload['data']['command'];

            try {
                $serializable = @unserialize($command, ['allowed_classes' => true]);
            } catch (\Throwable $exception) {
                continue;
            }

            if (!$serializable instanceof SerializableClosure) {
                continue;
            }

            try {
                $closure = $serializable->getClosure();
                $staticVariables = (new ReflectionFunction($closure))->getStaticVariables();
            } catch (\Throwable $exception) {
                continue;
            }

            if (($staticVariables['uuid'] ?? null) === $indexJob->uuid) {
                return $queueJob;
            }
        }

        return null;
    }
}