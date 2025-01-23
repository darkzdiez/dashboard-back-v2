<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Junges\ACL\Models\Permission;

use AporteWeb\Dashboard\Controllers\PermissionController;
use AporteWeb\Dashboard\Controllers\GroupController;
use AporteWeb\Dashboard\Controllers\CronController;
use AporteWeb\Dashboard\Controllers\UserController;
use AporteWeb\Dashboard\Controllers\NotesController;
use AporteWeb\Dashboard\Controllers\JobsController;
use AporteWeb\Dashboard\Controllers\DatabaseController;
use AporteWeb\Dashboard\Controllers\ProfileController;
use AporteWeb\Dashboard\Controllers\NewsController;

Route::group(['middleware' => ['auth']], function () {
    Route::group([
        'prefix' => 'notes',
        'as'     => 'notes',
    ], function() {
        Route::get ('/{area}/{refid}', [NotesController::class, 'get'])->name('.get');
        Route::get ('/{id}',           [NotesController::class, 'find'])->name('.find');
        Route::post('/{id}/delete',    [NotesController::class, 'delete'])->name('.delete');
        Route::post('/{area}/{refid?}', [NotesController::class, 'save'])->name('.save');
    });
    Route::group([
        'prefix' => 'database',
        'as'     => 'database',
    ], function() {
        Route::get ('/tables', [DatabaseController::class, 'tables'])->name('.tables');
        Route::get ('/groups', [DatabaseController::class, 'groups'])->name('.groups');
        Route::post('/groups', [DatabaseController::class, 'groupsStore'])->name('.groupsStore');
        Route::post('/group/delete', [DatabaseController::class, 'groupDelete'])->name('.groupDelete');
        
        Route::post('/group/table/add', [DatabaseController::class, 'groupTableAdd'])->name('.groupTableAdd');
        Route::post('/group/table/remove', [DatabaseController::class, 'groupTableRemove'])->name('.groupTableRemove');
        Route::post('/group/seeders/generate', [DatabaseController::class, 'groupSeedersGenerate'])->name('.groupSeedersGenerate');
        Route::post('/group/seeders/execute', [DatabaseController::class, 'groupSeedersExecute'])->name('.groupSeedersExecute');
        Route::post('/table/seeders/generate', [DatabaseController::class, 'tableSeedersGenerate'])->name('.tableSeedersGenerate');
        Route::post('/table/seeders/execute', [DatabaseController::class, 'tableSeedersExecute'])->name('.tableSeedersExecute');
        
        Route::post('/backup/generate', [DatabaseController::class, 'backupGenerate'])->name('.backupGenerate');
        Route::get ('/backup/list', [DatabaseController::class, 'listBackups'])->name('.listBackups');
        Route::post('/backup/delete', [DatabaseController::class, 'backupDelete'])->name('.backupDelete');
    });
    Route::group([
        'prefix' => 'profile',
        'as'     => 'profile',
    ], function() {
        Route::get ('/',                [ProfileController::class, 'getProfile'])->name('.getProfile');
        Route::post('/',                [ProfileController::class, 'saveProfile'])->name('.saveProfile');
        Route::post('/change-password', [ProfileController::class, 'changePassword'])->name('.changePassword');
    });

    Route::group([
        'prefix' => 'jobs',
        'as'     => 'jobs',
    ], function() {
        Route::any ('/',        [JobsController::class, 'all'])->name('.all');
        Route::get ('/top-bar', [JobsController::class, 'topBar'])->name('.topBar');
    });

    Route::group([
        'prefix' => 'news',
        'as'     => 'news',
    ], function() {
        Route::post('/',                [NewsController::class, 'all'])->name('.all');
        Route::get ('/top-bar',         [NewsController::class, 'topBar'])->name('.topBar');
        Route::get ('/{id}',            [NewsController::class, 'find'])->name('.find');
        Route::post('/store/{id?}',     [NewsController::class, 'store'])->name('.store');
        Route::post('/{id}/delete',     [NewsController::class, 'delete'])->name('.delete');
    });

    Route::group([
        'prefix' => 'permission',
        'as'     => 'permission',
    ], function() {
        Route::post('/',             [PermissionController::class, 'all'])->name('.all');
        Route::get ('/{id}',         [PermissionController::class, 'find'])->name('.find');
        Route::post('/store/{id?}',  [PermissionController::class, 'store'])->name('.store');
        Route::get ('/delete/{id}',  [PermissionController::class, 'delete'])->name('.delete');
        Route::get ('/restore/{id}', [PermissionController::class, 'restore'])->name('.restore');
        Route::post('/generate-seed-from-data-model',  [PermissionController::class, 'generateSeedFromDataModel'])->name('.generateSeedFromDataModel');
    });
    
    Route::group([
        'prefix' => 'group',
        'as'     => 'group',
    ], function() {
        Route::post('/',             [GroupController::class, 'all'])->name('.all');
        Route::get ('/{id}',         [GroupController::class, 'find'])->name('.find');
        Route::post('/store/{id?}',  [GroupController::class, 'store'])->name('.store');
        Route::get ('/delete/{id}',  [GroupController::class, 'delete'])->name('.delete');
        Route::get ('/restore/{id}', [GroupController::class, 'restore'])->name('.restore');
        Route::post('/list-select',  [GroupController::class, 'listSelect'])->name('.listSelect');
        Route::post('/generate-seed-from-data-model',  [GroupController::class, 'generateSeedFromDataModel'])->name('.generateSeedFromDataModel');
    });
    
    Route::group([
        'prefix' => 'cron',
        'as'     => 'cron',
    ], function() {
        Route::post('/',             [CronController::class, 'all'])->name('.all');
        Route::get ('/{id}',         [CronController::class, 'find'])->name('.find');
        Route::post('/store/{id?}',  [CronController::class, 'store'])->name('.store');
        Route::get ('/delete/{id}',  [CronController::class, 'delete'])->name('.delete');
        Route::get ('/restore/{id}', [CronController::class, 'restore'])->name('.restore');
        Route::post('/list-select',  [CronController::class, 'listSelect'])->name('.listSelect');
    });

    Route::group([
        'prefix' => 'user',
        'as'     => 'user',
    ], function() {
        Route::post('/',             [UserController::class, 'all'])->name('.all');
        Route::get ('/{id}',         [UserController::class, 'find'])->name('.find');
        Route::post('/store/{id?}',  [UserController::class, 'store'])->name('.store');
        Route::get ('/delete/{id}',  [UserController::class, 'delete'])->name('.delete');
        Route::get ('/restore/{id}', [UserController::class, 'restore'])->name('.restore');
        Route::post('/list-select',  [UserController::class, 'listSelect'])->name('.listSelect');
        Route::post('/generate-seed-from-data-model',  [UserController::class, 'generateSeedFromDataModel'])->name('.generateSeedFromDataModel');
        Route::post('/login-as',     [UserController::class, 'loginAs'])->name('.loginAs');
        Route::post('/return-to-original-user' , [UserController::class, 'returnToOriginalUser'])->name('.returnToOriginalUser');
    });

});

Route::get('check-auth', function () {
    //try {
        $user = auth()->guard('web')->user();
        if ( ! $user ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not authenticated',
            ]);
        }
        // get all permissions
        $allPermissions = Permission::get();
        $permissions = [];
        /*
        foreach ($allPermissions as $permission) {
            $permissions[$permission->name] = [
                'description' => $permission->description,
                'access' => false,
            ];
        }
        */
        // Permission::create(['name' => 'add employees']);
        // $user->syncPermissions(['user-create']);
        // get all user permissions
        // $userPermissions = $user->getAllPermissions();
        $userPermissions = $user->getOrgAllPermissions();
        foreach ($userPermissions as $permission) {
            // $permissions[$permission->name]['access'] = true;
            $permissions[$permission->name] = [
                'description' => $permission->description,
                'access' => true,
            ];
        }

        $config = \App\Models\ConfigGeneral::select([
            'cotizacion_default_tipo_de_contenedor_id',
        ])->first()->toArray();

        return response()->json([
            'user' => [
                'id'                     => $user->id,
                'name'                   => $user->name,
                'email'                  => $user->email,
                'username'               => $user->username,
                'original_user'          => session()->get('original_user'),
                'environment'            => $user->environment,
                'permissions'            => $permissions,
                'organization_id'        => $user->organization->id,
                'organization_name'      => $user->organization->name,
                'organization_type_id'   => $user->organization->type->id,
                'organization_type_name' => $user->organization->type->name,
                'organization_logo_url'  => $user->organization->logo_url,
                'organization_favicon_url'  => $user->organization->favicon_url,
            ],
            'status' => 'success',
            'config' => $config,
        ]);
    /*} catch (\Throwable $th) {
        return response()->json([
            'status' => 'error'
        ]);
    }*/
});

use Illuminate\Support\Facades\Auth;
Route::post('login', function (Request $request) {
    // check if username is email or username
    if ( filter_var($request->username, FILTER_VALIDATE_EMAIL) ) {
        $credentials = [
            'email' => $request->username,
            'password' => $request->password,
        ];
    } else {
        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];
    }
    /*
    Antes en Laravel 10
    if ( ! auth()->guard('web')->attempt($credentials, true) ) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    return redirect()->intended('api/check-auth');
    */
    // Laravel 11
    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();

        return redirect()->intended('api/check-auth');
    }

    return back()->withErrors([
        'email' => 'The provided credentials do not match our records.',
    ])->onlyInput('email');
});

Route::get('logout', function () {
    auth()->logout();
    session()->invalidate();
    return response()->json(['message' => 'Logged out']);
});

Route::get('server-stats', function () {
    $stats = [
        'time' => [
            'date' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'timestamp' => time(),
        ],

        'php' => [
            'version' => phpversion(),
        ],
        'laravel' => [
            'version' => app()->version(),
        ],
        'mysql' => [
            'version' => DB::select('select version() as version')[0]->version,
        ],
        'os' => [
            'name' => php_uname('s'),
            'release' => php_uname('r'),
            'version' => php_uname('v'),
            'machine' => php_uname('m'),
        ],
        'cpu' => [
            'cores' => shell_exec('nproc'),
            'model' => shell_exec('cat /proc/cpuinfo | grep "model name" | head -1 | cut -d ":" -f2'),
        ],
        'ram' => [
            'total' => shell_exec('free -m | grep Mem | awk \'{print $2}\''),
            'used' => shell_exec('free -m | grep Mem | awk \'{print $3}\''),
            'free' => shell_exec('free -m | grep Mem | awk \'{print $4}\''),
            'shared' => shell_exec('free -m | grep Mem | awk \'{print $5}\''),
            'cache' => shell_exec('free -m | grep Mem | awk \'{print $6}\''),
            'available' => shell_exec('free -m | grep Mem | awk \'{print $7}\''),
        ],
        'disk' => [
            'total' => disk_total_space('/'),
            'free' => disk_free_space('/'),
            'used' => disk_total_space('/') - disk_free_space('/'),
        ],
        'uptime' => shell_exec('uptime -p'),
    ];
    return response()->json($stats);
});
Route::get('timestamp', function () {
    return time();
});