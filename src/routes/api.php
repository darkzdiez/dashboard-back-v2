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

Route::group(['middleware' => ['auth']], function () {
    Route::group([
        'prefix' => 'notes',
        'as'     => 'notes',
    ], function() {
        Route::get ('/{area}/{refid}', [NotesController::class, 'get']);
        Route::get ('/{id}',           [NotesController::class, 'find']);
        Route::post('/{id}/delete',    [NotesController::class, 'delete']);
        Route::post('/{area}/{refid}', [NotesController::class, 'save']);
    });
    Route::group([
        'prefix' => 'database',
        'as'     => 'database',
    ], function() {
        Route::get ('/tables', [DatabaseController::class, 'tables']);
        Route::get ('/groups', [DatabaseController::class, 'groups']);
        Route::post('/groups', [DatabaseController::class, 'groupsStore']);
        Route::post('/group/delete', [DatabaseController::class, 'groupDelete']);
        
        Route::post('/group/table/add', [DatabaseController::class, 'groupTableAdd']);
        Route::post('/group/table/remove', [DatabaseController::class, 'groupTableRemove']);
        Route::post('/group/seeders/generate', [DatabaseController::class, 'groupSeedersGenerate']);
        Route::post('/group/seeders/execute', [DatabaseController::class, 'groupSeedersExecute']);
        Route::post('/table/seeders/generate', [DatabaseController::class, 'tableSeedersGenerate']);
        Route::post('/table/seeders/execute', [DatabaseController::class, 'tableSeedersExecute']);
    });
    Route::group([
        'prefix' => 'profile',
        'as'     => 'profile',
    ], function() {
        Route::get ('/',                [ProfileController::class, 'getProfile']);
        Route::post('/',                [ProfileController::class, 'saveProfile']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
    });

    Route::group([
        'prefix' => 'jobs',
        'as'     => 'jobs',
    ], function() {
        Route::any ('/',        [JobsController::class, 'all']);
        Route::get ('/top-bar', [JobsController::class, 'topBar']);
    });

    Route::group([
        'prefix' => 'permission',
        'as'     => 'permission',
    ], function() {
        Route::post('/',             [PermissionController::class, 'all']);
        Route::get ('/{id}',         [PermissionController::class, 'find']);
        Route::post('/store/{id?}',  [PermissionController::class, 'store']);
        Route::get ('/delete/{id}',  [PermissionController::class, 'delete']);
        Route::get ('/restore/{id}', [PermissionController::class, 'restore']);
        Route::post('/generate-seed-from-data-model',  [PermissionController::class, 'generateSeedFromDataModel']);
    });
    
    Route::group([
        'prefix' => 'group',
        'as'     => 'group',
    ], function() {
        Route::post('/',             [GroupController::class, 'all']);
        Route::get ('/{id}',         [GroupController::class, 'find']);
        Route::post('/store/{id?}',  [GroupController::class, 'store']);
        Route::get ('/delete/{id}',  [GroupController::class, 'delete']);
        Route::get ('/restore/{id}', [GroupController::class, 'restore']);
        Route::post('/list-select',  [GroupController::class, 'listSelect']);
        Route::post('/generate-seed-from-data-model',  [GroupController::class, 'generateSeedFromDataModel']);
    });
    
    Route::group([
        'prefix' => 'cron',
        'as'     => 'cron',
    ], function() {
        Route::post('/',             [CronController::class, 'all']);
        Route::get ('/{id}',         [CronController::class, 'find']);
        Route::post('/store/{id?}',  [CronController::class, 'store']);
        Route::get ('/delete/{id}',  [CronController::class, 'delete']);
        Route::get ('/restore/{id}', [CronController::class, 'restore']);
        Route::post('/list-select',  [CronController::class, 'listSelect']);
    });

    Route::group([
        'prefix' => 'user',
        'as'     => 'user',
    ], function() {
        Route::post('/',             [UserController::class, 'all']);
        Route::get ('/{id}',         [UserController::class, 'find']);
        Route::post('/store/{id?}',  [UserController::class, 'store']);
        Route::get ('/delete/{id}',  [UserController::class, 'delete']);
        Route::get ('/restore/{id}', [UserController::class, 'restore']);
        Route::post('/list-select',  [UserController::class, 'listSelect']);
        Route::post('/generate-seed-from-data-model',  [UserController::class, 'generateSeedFromDataModel']);
        Route::post('/login-as',     [UserController::class, 'loginAs']);
        Route::post('/return-to-original-user' , [UserController::class, 'returnToOriginalUser']);
    });

});

Route::get('check-auth', function () {
    try {
        $user = auth()->guard('web')->user();
        // get all permissions
        $allPermissions = Permission::get();
        $permissions = [];
        foreach ($allPermissions as $permission) {
            $permissions[$permission->name] = [
                'description' => $permission->description,
                'access' => false,
            ];
        }
        // Permission::create(['name' => 'add employees']);
        // $user->syncPermissions(['user-create']);
        // get all user permissions
        $userPermissions = $user->getAllPermissions();
        foreach ($userPermissions as $permission) {
            $permissions[$permission->name]['access'] = true;
        }
        return response()->json([
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'username'      => $user->username,
                'original_user' => session()->get('original_user'),
                'environment'   => $user->environment,
                'permissions'   => $permissions,
            ],
            'status' => 'success'
        ]);
    } catch (\Throwable $th) {
        return response()->json([
            'status' => 'error'
        ]);
    }
});

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
    if ( ! auth()->guard('web')->attempt($credentials, true) ) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    return redirect()->intended('api/check-auth');
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