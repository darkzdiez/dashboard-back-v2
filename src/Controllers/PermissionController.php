<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Junges\ACL\Models\Permission;

class PermissionController extends Controller {
    public function all() {
        return Permission::orderBy('group_prefix', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(100);
    }

    public function find($id) {
        return Permission::find($id);
    }

    public function store(Request $request, $id = null) {
        // validate the request
        $request->validate([
            'name'        => 'required|string|max:255|unique:permissions' . ($id ? ",name,$id" : ''),
            'guard_name'  => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        // store a new permission
        if ($id) {
            $permission = Permission::find($id);
        } else {
            $permission = new Permission;
            $permission->uuid = Str::uuid();
        }
        if ( request()->has('assistant') && request()->assistant == 'crud-basic' ) {
            $permissions = [
                '-data-import' => 'Importar',
                '-data-export' => 'Exportar',
                '-restore'     => 'Restaurar',
                '-delete'      => 'Eliminar',
                '-edit'        => 'Editar',
                '-create'      => 'Crear'
            ];
            foreach ($permissions as $key => $description) {
                $permission = new Permission;
                $permission->uuid         = Str::uuid();
                $permission->group_prefix = $request->group_prefix;
                $permission->name         = $request->name . $key;
                $permission->guard_name   = $request->guard_name;
                $permission->description  = $description . ' ' . $request->description;
                $permission->save();
            }
        } else {
            $permission->group_prefix = $request->group_prefix;
            $permission->name         = Str::slug($request->name);
            $permission->guard_name   = $request->guard_name;
            $permission->description  = $request->description ? $request->description : $request->name;
            $permission->save();
        }
        return $permission;
    }

    public function delete($id) {
        $permission = Permission::find($id);
        $permission->delete();
        return $permission;
    }

    public function restore($id) {
        $permission = Permission::withTrashed()->find($id);
        $permission->restore();
        return $permission;
    }

    public function generateSeedFromDataModel() {
        $data = Permission::all();
        $data = $data->map(function($item) {
            return [
                'name'         => $item->name,
                'guard_name'   => $item->guard_name,
                'description'  => $item->description,
                'group_prefix' => $item->group_prefix,
            ];
        });
        
        // generate seed on database/seeders/permissions.json
        $path = database_path('seeders/permissions.json');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['message' => 'Seed generated'], 200);
    }
}