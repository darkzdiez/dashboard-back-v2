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
use AporteWeb\Dashboard\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;

Route::group(['middleware' => ['auth']], function () {
    Route::group([
        'prefix' => 'notes',
        'as'     => 'notes',
    ], function() {
        Route::get ('/{area}/{refid}', [NotesController::class, 'get'])->name('.get');
        Route::get ('/{id}',           [NotesController::class, 'find'])->name('.find');
        Route::post('/{id}/delete',    [NotesController::class, 'delete'])->name('.delete');
        Route::post('/{area}/{refid?}', [NotesController::class, 'save'])->name('.save');
        Route::post('/{area}/{refid?}/pagination', [NotesController::class, 'pagination'])->name('.pagination');
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
        Route::delete('/history/{scope?}', [JobsController::class, 'clearHistory'])->name('.clearHistory');
        Route::get ('/{uuid}/callback', [JobsController::class, 'callback'])->name('.callback');
        Route::get ('/{uuid}/queries', [JobsController::class, 'queries'])->name('.queries');
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
        Route::post('/list-select',  [PermissionController::class, 'listSelect'])->name('.listSelect');
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
        Route::post('/{id}/execute-now', [CronController::class, 'executeNow'])->name('.executeNow');
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

Route::get('check-auth', [AuthController::class, 'checkAuth']);

Route::post('login', [AuthController::class, 'login']);
Route::post('recover-password', [AuthController::class, 'recoverPassword']);
Route::get('logout', [AuthController::class, 'logout']);

Route::get('timestamp', function () {
    return time();
});