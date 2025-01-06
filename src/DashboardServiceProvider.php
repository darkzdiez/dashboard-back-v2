<?php
namespace AporteWeb\Dashboard;

/**
 * DashboardServiceProvider
 *
 * The service provider for the modules. After being registered
 * it will make sure that each of the modules are properly loaded
 * i.e. with their routes, views etc.
 *
 * @author Alfonzo Diez <alfonzodiez@gmail.com>
 * @package aporteweb/dashboard-back-v2
 */

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessed;

class DashboardServiceProvider extends ServiceProvider {
    public function register(): void {
        // App::register('Krucas\Notification\NotificationServiceProvider');
        // App::alias('Notification','Krucas\Notification\Facades\Notification');
    }
    public function boot(): void {
        $request = request()->all();
        array_walk_recursive($request, function (&$item, $clave) {
            // el siguiente codigo es para que los campos undefined, null y NULL pero que llegan como string
            // se conviertan en null realmente
            // si el campo es un string y tiene espacios al inicio o al final, se eliminan
            if (is_string($item)) {
                $item = trim($item);
            }
            if (strtolower($item) == 'undefined' || strtolower($item) == 'null' || strlen($item) == 0) {
                $item = null;
            }
            // Detectar si es un booleano
            if (strtolower($item) == 'true') {
                $item = true;
            }
            if (strtolower($item) == 'false') {
                $item = false;
            }
        });
        request()->merge($request);

        if (config('app.debug')){
            $assets_version = hash('md5', rand());
        } else {
            $assets_version = '16';
            if ( env('ASSETS_VERSION') ) {
                $assets_version = env('ASSETS_VERSION');
            } else {
                $git_refs_dir = base_path('.git/refs/heads');
                $files = scandir($git_refs_dir, SCANDIR_SORT_ASCENDING);
                // Filtrar los archivos para excluir "." y ".."
                $branch_files = array_filter($files, function ($file) {
                    return $file !== '.' && $file !== '..';
                });
    
                // Obtener el primer archivo (rama)
                $first_branch_file = reset($branch_files);
    
                if ($first_branch_file) {
                    $path_file = $git_refs_dir . '/' . $first_branch_file;
                    if (file_exists($path_file)) {
                        $assets_version = trim(substr(file_get_contents($path_file), 4));
                        // Resto de tu lógica aquí...
                    } else {
                        // "El archivo de la rama no existe.";
                    }
                } else {
                    // "No se encontraron archivos de ramas en la carpeta .git/refs/heads.";
                }            
            }
        }
        view()->share([
            'assets_version'      => $assets_version,
        ]);

        /*
        // Añade el metodo toRawSql a los query builder
        \Illuminate\Database\Query\Builder::macro('toRawSql', function(){
            return array_reduce($this->getBindings(), function($sql, $binding){
                return preg_replace('/\?/', is_numeric($binding) ? $binding : "'".$binding."'" , $sql, 1);
            }, $this->toSql());
        });
        \Illuminate\Database\Eloquent\Builder::macro('toRawSql', function(){
            return ($this->getQuery()->toRawSql());
        });
        */

        // Load Migrations
        if(is_dir(__DIR__.'/migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/migrations');
        }

        // Load Web Routes
        if(file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->namespace('\AporteWeb\Dashboard\Controllers')
                ->group(__DIR__.'/routes/web.php');
        }

        // Load Api Routes
        if(file_exists(__DIR__.'/routes/api.php')) {
            Route::prefix('api')
                ->middleware('api')
                ->namespace('\AporteWeb\Dashboard\Controllers')
                ->group(__DIR__.'/routes/api.php');
        }

        // Load Translations
        if(is_dir(__DIR__.'/resources/lang')) {
            $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'Dashboard');
            // $this->publishes([
            //     __DIR__.'/lang' => resource_path('lang/vendor/aporteweb/dashboard'),
            // ]);
        }

        // Load the views
        if(is_dir(__DIR__.'/resources/views')) {
            $this->loadViewsFrom(__DIR__.'/resources/views', 'Dashboard');
        }

        Queue::after(function (JobProcessed $event) {
            /*
            if (!Schema::hasTable('processed_jobs')) {
                Schema::create('processed_jobs', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->string('connection');
                    $table->string('queue')->index();
                    $table->longText('payload');
                    $table->timestamp('processed_at')->useCurrent();
                });
            }
            */
            \DB::table('processed_jobs')->insert([
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'payload' => json_encode($event->job->payload()),
                'processed_at' => \Carbon\Carbon::Now()
            ]);
        });   
    }
}
