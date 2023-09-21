<?php

if (! function_exists('watchdog')) {
    function watchdog()
    {
        return new \AporteWeb\Dashboard\Libs\Watchdog;
    }
}

if (! function_exists('sniff')) {
    function sniff($permission, $guardName = 'web')
    {
        return watchdog()->sniff($permission, $guardName);
    }
}

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

if (!function_exists('__dashboardTask')) {
    function __dashboardTask($title = null, $description = null, $group = 'jobs', $icon = null, $level = 'info', Closure $next = null) {
        // Doc
        // * * * * * cd /path-to-your-project && php artisan queue:work >> /dev/null 2>&1
        // php artisan queue:work
        // $migtrate = MigrateData::dispatch();
        // */2	*	*	*	*	
        // php artisan queue:work --timeout=12000 --stop-when-empty --once
        // Generar el uuid
        // /usr/local/bin/php -d register_argc_argv=On -f /home/d10osolecom/deluca.osole.com.ar/artisan queue:work --timeout=12000 --stop-when-empty --once
        $uuid  = (string) Str::uuid();
        $queue = 'default';
        $user_id = auth()->user()->id ?? null;
        // Insertar en la tabla de index_jobs
        DB::table('index_jobs')->insert([
            'uuid'           => $uuid,
            'queue'          => $queue,
            'title'          => $title,
            'description'    => $description,
            'group'          => $group,
            'icon'           => $icon,
            'level'          => $level,
            'callback'       => null, // es un tipo de dato json
            'time_execution' => 0,
            'memory_usage'   => 0,
            'status'         => 'waiting',
            'user_id'        => $user_id,
            'created_at'     => \Carbon\Carbon::Now(),
            'updated_at'     => \Carbon\Carbon::Now(),
        ]);
        // Crear en la carpeta dashboard-task, un achivo con el nombre del uuid y con el contenido de status waiting
        Storage::disk('local')->put('dashboard-task/' . $uuid . '.json', json_encode(['status' => 'waiting'], JSON_PRETTY_PRINT));
        
        Cache::forget('jobs.' . $user_id);
        
        // Utilizar dispatch para añadir la tarea a la cola, esto lo almacenará en la tabla jobs
        $dispatch = dispatch(function () use ($uuid, $next, $user_id) {

            // Se define el tiempo limite de ejecución y la memoria
            set_time_limit(0); // 0 = no time limit
            ini_set('memory_limit', '-1'); // -1 = no memory limit
    
            // se debe saber cuanto tiempo se demora la tarea en ejecutarse y cuanta memoria utiliza
            $start = microtime(true);
            $memory_usage = memory_get_usage();

            DB::table('index_jobs')->where('uuid', $uuid)->update([
                'status' => 'running',
            ]);
            // Cambiar el status del archivo a running
            Storage::disk('local')->put('dashboard-task/' . $uuid . '.json', json_encode(['status' => 'running'], JSON_PRETTY_PRINT));
            Cache::forget('jobs.' . $user_id);

            try {
                // Eejecutar la clausura y retornar el resultado
                $callback = $next();

                // Calcular el tiempo y la memoria utilizada
                $time_execution = microtime(true) - $start;
                $memory_usage = memory_get_usage() - $memory_usage;

                // Almacenar el resultado de callback en la tabla index_jobs
                DB::table('index_jobs')->where('uuid', $uuid)->update([
                    'callback' => json_encode($callback),
                    'status' => 'finish',
                    'time_execution' => $time_execution,
                    'memory_usage' => $memory_usage,
                    'updated_at' => \Carbon\Carbon::Now(),
                ]);

                // Cambiar el status del archivo a finish
                Storage::disk('local')->put('dashboard-task/' . $uuid . '.json', json_encode(['status' => 'finish'] + $callback, JSON_PRETTY_PRINT));
                Cache::forget('jobs.' . $user_id);
            } catch (\Throwable $th) {

                $error = [
                    'status' => 'failed',
                    'message' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ];

                // Almacenar el error en la tabla index_jobs
                DB::table('index_jobs')->where('uuid', $uuid)->update([
                    'callback' => json_encode($error, JSON_PRETTY_PRINT),
                    'status' => 'failed',
                    'updated_at' => \Carbon\Carbon::Now(),
                ]);

                // Cambiar el status del archivo a failed
                Storage::disk('local')->put('dashboard-task/' . $uuid . '.json', json_encode($error, JSON_PRETTY_PRINT));
                Cache::forget('jobs.' . $user_id);
            }
        })->onQueue($queue);
        return [
            'uuid' => $uuid,
            'dispatch' => $dispatch,
        ];
    }
}