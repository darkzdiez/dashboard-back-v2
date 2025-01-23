<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use AporteWeb\Dashboard\Models\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller {

    public function topBar() {
        $news = Cache::rememberForever('news-list-torbar', function () {
            return News::orderBy('created_at', 'desc')->limit(5)->get();
        });
        return response()->json($news);
    }
    
    public function all() {
        return News::orderBy('id', 'desc')
            ->paginate(100);
    }

    public function find($id) {
        $item = News::where('uuid', $id)->first();
        if ($item) {
            return $item;
        }
        return response()->json(['message' => 'Item not found'], 404);
    }

    public function store(Request $request, $id = null) {
        $request->validate([
            'title'             => 'required|string|max:255',
            'description_short' => 'required|string',
            'description'       => 'nullable|string',
        ]);
        // store a new item
        if ($id && ! $request->has('__form-input-copy') ) {
            $item = News::where('uuid', $id)->first();
        } else {
            $item = new News;
        }
        $item->title             = $request->title;
        $item->description_short = $request->description_short;
        $item->description       = $request->description;
        $item->save();

        // limpiar cache
        Cache::forget('news-list-torbar');

        return $item;

    }

    public function delete($id) {
        $permission = News::find($id);
        $permission->delete();
        return $permission;
    }

    public function restore($id) {
        $permission = News::withTrashed()->find($id);
        $permission->restore();
        return $permission;
    }
}