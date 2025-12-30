<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use Junges\ACL\Models\Group;
use AporteWeb\Dashboard\Models\Group;

class GroupController extends Controller {
    public function all() {
        // sleep(10);
        // sniff($this->prefixPermission . '-*');
        $paginator = Group::with(['parent', 'users'])->orderBy('id', 'desc');
        if ( request()->has('filters') && is_array(request()->filters) ) {
            foreach (request()->filters as $key => $value) {
                $paginator->where($key, 'like', '%'.$value.'%');
            }
        }
        if ( request()->has('trash') && request()->trash == 1 ) {
            $paginator->onlyTrashed();
        }

        $paginator = $paginator->paginate(20);

        $data = $paginator->getCollection();
        $data->each(function ($item) {
            // $item->setHidden(['id']);
            if ( $item->organization ) {
                $item->organization->makeHidden(['id']);
            }
        });
        $paginator->setCollection($data);
    
        return $paginator;
    }


    public function find($id) {
        $group = Group::where('uuid', $id)->first();
        if ($group) {
            $group->permissions   = $group->permissions()->pluck('id');
            $group->notifications = $group->notifications()->pluck('key');
            return $group;
        }
        return response()->json(['message' => 'Group not found'], 404);
    }

    public function store(Request $request, $id = null) {
        // store a new item
        if ($id) {
            $item = Group::where('uuid', $id)->first();
        } else {
            $item = new Group;
        }        
        // validate the request
        $request->validate([
            'name'        => 'required|string|max:255|unique:groups' . ($id ? ",name,$item->id" : ''),
            'guard_name'  => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        $item->name        = $request->name;
        $item->guard_name  = $request->guard_name;
        $item->description = $request->description;
        $item->parent_id   = $request->parent_id;
        $item->save();

        $permissions = explode(',', $request->permissions);
        $permissions = array_filter($permissions);
        // sync permissions
        $item->permissions()->sync($permissions);

        $notifications = explode(',', $request->notifications);
        $notifications = array_filter($notifications);
        // sync notifications
        $item->notifications()->delete();
        foreach ($notifications as $notification) {
            $item->notifications()->create([ 'key' => $notification ]);
        }

        return $item;
    }

    public function delete($id) {
        $item = Group::where('uuid', $id)->first();
        $item->delete();
        return $item;
    }

    public function restore($id) {
        $item = Group::withTrashed()->where('uuid', $id)->first();
        $item->restore();
        return $item;
    }

    public function destroy($id) {
        $item = Group::withTrashed()->where('uuid', $id)->first();
        $item->forceDelete();
        return $item;
    }


    public function generateSeedFromDataModel() {
        $data = Group::all();
        $data = $data->map(function($item) {
            return [
                'name'         => $item->name,
                'guard_name'   => $item->guard_name,
                'description'  => $item->description,
            ];
        });
        
        // generate seed on database/seeders/groups.json
        $path = database_path('seeders/groups.json');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['message' => 'Seed generated'], 200);
    }

    public function listSelect(Request $request) {
        $data = Group::orderBy('id', 'desc');
        if ( request()->has('search') && strlen(request()->search) ) {
            $data->where(function($query) {
                $keys = ['name'];
                foreach ($keys as $key => $colName) {
                    $query->orWhere($colName, 'like', '%'.request()->search.'%');
                }
            });
        }
        if ( request()->has('optionKey') ) {
            $data->where('id', request()->optionKey);
            return $data->first();
        }
        return $data->get();
    }


}