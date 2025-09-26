<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use Junges\ACL\Models\Cron;
use AporteWeb\Dashboard\Models\Cron;

class CronController extends Controller {
    public function all() {
        // sniff($this->prefixPermission . '-*');
        $data = Cron::orderBy('id', 'asc');
        if ( request()->has('filters') && is_array(request()->filters) ) {
            foreach (request()->filters as $key => $value) {
                $data->where($key, 'like', '%'.$value.'%');
            }
        }
        if ( request()->has('trash') && request()->trash == 1 ) {
            $data->onlyTrashed();
        }
        return $data->paginate(20);
    }


    public function find($id) {
        $task = Cron::withTrashed()->with(['logs'])->where('uuid', $id)->first();
        if ($task) {
            return $task;
        }
        return response()->json(['message' => 'Cron not found'], 404);
    }

    public function run() {
        $path = base_path('storage/app/cron.php');
        $crons = Cron::orderBy('id', 'asc')->get();
        $content = view('Dashboard::cron', [
            'crons' => $crons,
        ])->render();
        
        file_put_contents($path, $content);
    }

    public function store(Request $request, $id = null) {
        // store a new item
        if ($id) {
            $item = Cron::withTrashed()->where('uuid', $id)->first();
        } else {
            $item = new Cron;
        }        
        // validate the request
        $request->validate([
            'description' => 'required',
            'method'      => 'required|in:exec,command',
            'command'     => 'required_if:method,command',
            'minute'      => 'required',
            'hour'        => 'required',
            'day'         => 'required',
            'month'       => 'required',
            'day_of_week' => 'required',
        ]);
        $item->description = $request->description;
        $item->method      = $request->method;
        $item->command     = $request->command;
        $item->minute      = $request->minute;
        $item->hour        = $request->hour; 
        $item->day         = $request->day;
        $item->month       = $request->month;
        $item->day_of_week = $request->day_of_week;
        $item->save();

        $this->run();

        return $item;
    }

    public function executeNow($id) {
        $item = Cron::withTrashed()->where('uuid', $id)->first();
        if (!$item) {
            return response()->json(['message' => 'Cron not found'], 404);
        }

        try {
            $output = '';
            $status = 'success';
            $error = '';
            if ($item->method == 'exec') {
                $result = null;
                exec($item->command . ' 2>&1', $outputLines, $result);
                $output = implode("\n", $outputLines);
                if ($result !== 0) {
                    $status = 'failure';
                }
            } elseif ($item->method == 'command') {
                try {
                    \Artisan::call($item->command);
                    $output = \Artisan::output();
                } catch (\Exception $e) {
                    $status = 'failure';
                    $error = $e->getMessage();
                }
            }
            
            \DB::table('cron_log')->insert([
                'cron_id' => $item->id,
                'run_at' => \Carbon\Carbon::now(),
                'run_status' => $status,
                'output' => $output,
                'error' => $error,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);

            if ($status === 'failure') {
                return response()->json(['message' => 'Error executing cron job', 'output' => $output, 'error' => $error], 500);
            }
        } catch (\Exception $e) {
            \DB::table('cron_log')->insert([
                'cron_id' => $item->id,
                'run_at' => \Carbon\Carbon::now(),
                'run_status' => 'failure',
                'output' => '',
                'error' => $e->getMessage(),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);
            return response()->json(['message' => 'Error executing cron job: ' . $e->getMessage()], 500);
        }
        return response()->json(['message' => 'Cron job executed successfully', 'output' => $output]);
    }

    public function delete($id) {
        $item = Cron::where('uuid', $id)->first();
        $item->delete();
        $this->run();
        return $item;
    }

    public function restore($id) {
        $item = Cron::withTrashed()->where('uuid', $id)->first();
        $item->restore();
        $this->run();
        return $item;
    }

    public function destroy($id) {
        $item = Cron::withTrashed()->where('uuid', $id)->first();
        $item->forceDelete();
        $this->run();
        return $item;
    }

    public function listSelect(Request $request) {
        $data = Cron::orderBy('id', 'desc');
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