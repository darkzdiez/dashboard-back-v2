<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use AporteWeb\Dashboard\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Cache\RedisStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

class UserController extends Controller {
    public function all(Request $request) {
        // sniff('group-*');
        // traer la organización, pero solo el nombre
        $paginator = User::with(['organization' => function($query) {
            $query->select('id', 'uuid', 'name');
        }, 'groups:name,uuid'])
            ->orderBy('id', 'desc');
        $filters = $request->input('filters', []);
        if ( is_array($filters) ) {
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $value = array_values(array_filter(array_map(function ($item) {
                        if (!is_scalar($item)) {
                            return null;
                        }
                        $normalized = trim((string) $item);
                        return $normalized === '' ? null : $normalized;
                    }, $value)));
                }

                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    continue;
                }

                if ($key === 'group_uuid') {
                    $groupUuids = is_array($value) ? $value : [trim((string) $value)];
                    if (empty($groupUuids)) {
                        continue;
                    }
                    $paginator->whereHas('groups', function ($query) use ($groupUuids) {
                        $query->whereIn('uuid', $groupUuids);
                    });
                    continue;
                }

                if ($key === 'organization_uuid') {
                    $organizationUuids = is_array($value) ? $value : [trim((string) $value)];
                    if (empty($organizationUuids)) {
                        continue;
                    }
                    $paginator->whereHas('organization', function ($query) use ($organizationUuids) {
                        $query->whereIn('uuid', $organizationUuids);
                    });
                    continue;
                }

                if (is_scalar($value)) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    $paginator->where($key, 'like', '%'.$value.'%');
                }
            }
        }
        if ( $request->boolean('trash') ) {
            $paginator->onlyTrashed();
        }

        $paginator = $paginator->paginate(20);

        $data = $paginator->getCollection();
        $data->each(function ($item) {
            $item->setHidden(['id']);
            if ( $item->organization ) {
                $item->organization->makeHidden(['id']);
            }
            // hide the pivot table
            $item->groups->each(function($group) {
                $group->makeHidden(['pivot']);
            });
        });
        $paginator->setCollection($data);
    
        return $paginator;
    }

    public function find($id) {
        // sleep(3);
        $item = User::where('uuid', $id)->first();
        if ($item) {
            $item->groups = $item->groups()->pluck('uuid');
            return $item;
        }
        return response()->json(['message' => 'Item not found'], 404);    
    }

    public function store(Request $request, $id = null) {
        // store a new user
        if ($id) {
            $user = User::where('uuid', $id)->first();
        } else {
            $user = new User;
        }

        // Capturar grupos originales antes de modificar (solo si el usuario ya existe)
        $originalGroupIds = $user && $user->exists ? $user->groups()->pluck('id')->all() : [];

        // validate the request
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users' . ($id ? ",username,$user->id" : ''),
            'email'    => 'required|string|email|max:255|unique:users' . ($id ? ",email,$user->id" : ''),
            'password' => [
                $id ? 'nullable' : 'required',
                'string',
                'min:6',
            ]
        ]);
        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->organization_id = $request->organization_id;
        $user->login_with_password = $request->boolean('login_with_password', true);
        $user->login_with_google = $request->boolean('login_with_google', false);
        $user->access_testing = $request->boolean('access_testing', false);
        $user->access_production = $request->boolean('access_production', false);
        
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->save();
        // Sincronizar grupos y determinar diferencias
        $groupUuids = is_array($request->groups)
            ? array_filter($request->groups)
            : array_filter(explode(',', (string) $request->groups));
        if (count($groupUuids)) {
            // groups tiene los uuids de los grupos, debemos convertirlos a ids
            $newGroupIds = Group::whereIn('uuid', $groupUuids)->pluck('id')->all();
            $user->groups()->sync($newGroupIds);
        } else {
            $newGroupIds = [];
            $user->groups()->detach();
        }

        // Log de cambios en grupos (estandarizado vía ActivityLogger)
        $allIds = array_values(array_unique(array_merge($originalGroupIds, $newGroupIds)));
        $labelsById = $allIds
            ? Group::whereIn('id', $allIds)->pluck('name', 'id')->toArray()
            : [];

        $actorName = Auth::user() ? Auth::user()->name : 'system';
        $description = $id
            ? "El usuario {$actorName} actualizó los grupos del usuario {$user->name}"
            : "El usuario {$actorName} asignó grupos al usuario {$user->name}";

        activity()
            ->onModel($user)
            ->withRef('uuid', $user->uuid)
            ->forEvent($id ? 'update' : 'create')
            ->describe($description)
            ->logRelationSync('groups', $originalGroupIds, $newGroupIds, $labelsById, ['label' => 'Grupos']);

        return ['message' => 'Registro guardado'];
    }

    public function delete($id) {
        $item = User::where('uuid', $id)->first();
        $item->delete();
        return $item;
    }

    public function restore($id) {
        $item = User::withTrashed()->where('uuid', $id)->first();
        $item->restore();
        return $item;
    }

    public function destroy($id) {
        $item = User::withTrashed()->where('uuid', $id)->first();
        $item->forceDelete();
        return $item;
    }
    public function listSelect(Request $request) {
        $data = User::orderBy('id', 'desc');
        if ( $request->filled('search') ) {
            $search = $request->input('search');
            $data->where(function($query) use ($search) {
                $keys = ['name'];
                foreach ($keys as $key => $colName) {
                    $query->orWhere($colName, 'like', '%'.$search.'%');
                }
            });
        }
        if ( $request->filled('optionKey') ) {
            $data->where('id', $request->input('optionKey'));
            return $data->first();
        }
        return $data->get();    
    }

    public function loginAs(Request $request) {
        // Primero checamos si el usuario tiene permiso para hacer login como otro usuario
        $actor = clone auth()->user();
        if ( !$actor->can('login-as') ) {
            return response()->json(['message' => 'No tienes permiso para hacer login como otro usuario'], 403);
        }

        // Checamos si el usuario existe
        $targetUser = User::where('uuid', $request->target_id)->first();
        if ( !$targetUser ) {
            return response()->json(['message' => 'El usuario no existe'], 404);
        }

        // iniciamos sesión como el usuario
        auth()->login($targetUser);

        // añadimos el usuario a la sesión
        $request->session()->put('original_user', $actor->id);

        $startDescription = "El usuario {$actor->name} inició sesión como {$targetUser->name}";

        activity()
            ->causedBy($actor)
            ->onModel($targetUser)
            ->withRef('uuid', $targetUser->uuid)
            ->forEvent('impersonate-start')
            ->withProperties([
                'actor_id' => $actor->id,
                'actor_uuid' => $actor->uuid ?? null,
                'actor_name' => $actor->name,
                'target_id' => $targetUser->id,
                'target_uuid' => $targetUser->uuid,
                'target_name' => $targetUser->name,
            ])
            ->log($startDescription);

        return ['message' => 'Sesión iniciada como ' . $targetUser->name];
    }

    public function returnToOriginalUser() {
        $originalUserId = session()->pull('original_user');
        if ( $originalUserId ) {
            $impersonatedUser = auth()->user();
            $originalUser = User::find($originalUserId);

            if ($originalUser) {
                auth()->login($originalUser);

                $endDescription = "El usuario {$originalUser->name} regresó a su sesión tras impersonar a {$impersonatedUser?->name}";

                activity()
                    ->causedBy($originalUser)
                    ->onModel($impersonatedUser)
                    ->withRef('uuid', $impersonatedUser->uuid ?? null)
                    ->forEvent('impersonate-end')
                    ->withProperties([
                        'original_id' => $originalUser->id,
                        'original_uuid' => $originalUser->uuid ?? null,
                        'original_name' => $originalUser->name,
                        'impersonated_id' => $impersonatedUser?->id,
                        'impersonated_uuid' => $impersonatedUser?->uuid ?? null,
                        'impersonated_name' => $impersonatedUser?->name,
                    ])
                    ->log($endDescription);

                return ['message' => 'Sesión regresada a ' . $originalUser->name];
            }
        }
        return ['message' => 'No hay sesión para regresar'];        
    }

    public function resetPasswordMultiple(Request $request) {
        $request->validate([
            'uuids' => 'required|array',
        ]);

        $userUuids = $request->input('uuids', []);
        $actor = auth()->user();

        __dashboardTask(
            title: "Restablecer contraseñas",
            description: "Restableciendo contraseñas para " . count($userUuids) . " usuarios.",
            group: 'users',
            icon: 'mdi-lock-reset',
            level: 'info',
            queue: 'default',
            next: function() use ($userUuids, $actor) {
                 $resetter = new \AporteWeb\Dashboard\Libs\PasswordResetter();
                 return $resetter->resetMultiple($userUuids, $actor);
            }
        );

        return ['message' => 'Tarea de restablecimiento de contraseñas iniciada.'];
    }
}