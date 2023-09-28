<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller {
    public function all() {
        // sniff('group-*');
        $data = User::orderBy('id', 'desc');
        if ( request()->has('filters') && is_array(request()->filters) ) {
            foreach (request()->filters as $key => $value) {
                $data->where($key, 'like', '%'.$value.'%');
            }
        }
        if ( request()->has('trash') && request()->trash == 1 ) {
            $data->onlyTrashed();
        }
        return $data->paginate();
    }

    public function find($id) {
        $item = User::where('uuid', $id)->first();
        if ($item) {
            $item->groups = $item->groups()->pluck('id');
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
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->save();
        $groups = array_filter(explode(',', $request->groups));
        if ( count($groups) ) {
            $user->groups()->sync($groups);
        } else {
            $user->groups()->detach();
        }

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
        if ( request()->has('search') && strlen(request()->search) ) {
            $data->where(function($query) {
                $keys = ['name'];
                foreach ($keys as $key => $colName) {
                    $query->orWhere($colName, 'like', '%'.request()->search.'%');
                }
            });
        }
        return $data->get();    
    }

    public function loginAs(Request $request) {
        // Primero checamos si el usuario tiene permiso para hacer login como otro usuario
        $user = auth()->user();
        if ( !$user->can('login-as') ) {
            return response()->json(['message' => 'No tienes permiso para hacer login como otro usuario'], 403);
        }

        // Checamos si el usuario existe
        $targetUser = User::find($request->target_id);
        if ( !$targetUser ) {
            return response()->json(['message' => 'El usuario no existe'], 404);
        }

        // iniciamos sesión como el usuario
        auth()->login($targetUser);
        // añadimos el usuario a la sesión
        $request->session()->put('original_user', $user->id);

        return ['message' => 'Sesión iniciada como ' . $targetUser->name];
    }

    public function returnToOriginalUser() {
        $originalUserId = session()->pull('original_user');
        if ( $originalUserId ) {
            $originalUser = User::find($originalUserId);
            auth()->login($originalUser);
            return ['message' => 'Sesión regresada a ' . $originalUser->name];
        }
        return ['message' => 'No hay sesión para regresar'];        
    }
}