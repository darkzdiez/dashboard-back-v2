<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use AporteWeb\Dashboard\Models\Note;

class NotesController extends Controller {
    public function get($area, $refid) {
        return Note::with('user')
            ->where('area', $area)
            ->where('refid', $refid)
            ->orderByDesc('id')
            ->get();
    }
    public function find($id) {
        return Note::with('user')->find($id);
    }
    public function delete($id) {
        $item = Note::find($id);
        if ($item) {
            $item->delete();
            return $item;
        }
        abort(500, 'El registro que intenta eliminar no existe');
    }
    public function save(Request $request, $area = null, $refid = null) {
        // dd($request->all(), $request->has('title'));
        $request->validate([
            'title' => $request->has('title') ? 'required|string' : '',
            'content' => 'required|string',
        ]);
        try {
            DB::beginTransaction();
            // store a new item
            if ( $request->has('id') ) {
                $item = Note::find($request->id);
            } else {
                $item = new Note;
            }
            $item->area    = $area;
            if ( $refid ) {
                $item->refid   = $refid;
            }
            if ( $request->has('current_url') ) {
                $item->current_url   = $request->current_url;
            }
            $item->title   = $request->title;
            $item->content = $request->content;
            $item->level   = $request->level;
            $item->type    = $request->type;
            $item->user_id = auth()->user()->id;
            $item->save();
    

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'La nota fue guardada con éxito',
                /*
                'data'    => Note::with('user')
                    ->where('area', $area)
                    ->where('refid', $refid)
                    ->get()
                */
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al guardar la nota, '
                    . $e->getFile() . ':' . $e->getLine() . ' '
                    . $e->getMessage(),
                /*
                'data'    => Note::with('user')
                    ->where('area', $area)
                    ->where('refid', $refid)
                    ->get()
                */
            ], 500);
        }
    }
}