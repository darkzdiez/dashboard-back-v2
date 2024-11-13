<?php

namespace AporteWeb\Dashboard\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\DatabaseBackup;

class DatabaseController extends Controller {
    public function tables() {
        // Listar todas las tablas de la base de datos
        // return DB::connection()->getDoctrineSchemaManager()->listTableNames();
        // esto puede producir el siguiente error: Class "Doctrine\DBAL\Driver\AbstractMySQLDriver" not found
        // para solucionarlo, ejecutar: composer require doctrine/dbal
        $tables = DB::select('SHOW TABLES');
        return response()->json(array_map(function($table) {
            try {
                return $table->{'Tables_in_' . env('DB_DATABASE')};
            } catch (\Throwable $th) {
                dd($table);
            }
        }, $tables));
    }
    // Listar todos los grupos de tablas de la base de datos
    public function groups() {
        $groups = DB::table('table_groups')->get();

        // castear las tablas a un array
        foreach ($groups as $group) {
            $group->tables = json_decode($group->tables);
        }

        return response()->json($groups);
    }
    // Guardar un grupo de tablas en la base de datos
    public function groupsStore(Request $request) {
        $request->validate([
            'name'        => 'required|string|max:255|unique:table_groups',
        ]);
        // Si se envía el id, se actualiza el grupo
        if ( request()->has('group_id') ) {
            $request->validate([
                'group_id' => 'required|integer',
            ]);
            // Actualizar un grupo de tablas
            $group = DB::table('table_groups')->where('id', $request->group_id)->first();
            if ($group) {
                DB::table('table_groups')->where('id', $request->group_id)->update([
                    'name'        => $request->name,
                    // 'description' => $request->description,
                    // 'tables'      => json_encode($request->tables),
                ]);
                return response()->json(['message' => 'Group updated'], 200);
            }
            return response()->json(['message' => 'Group not found'], 404);
        } else {
            // Agregar un grupo de tablas
            $group = DB::table('table_groups')->insert([
                'name'        => $request->name,
            ]);
            return response()->json(['message' => 'Group added'], 200);
        }
    }
    // Eliminar un grupo de tablas de la base de datos
    // per sin eliminar las tablas
    public function groupDelete() {
        // validar los datos
        request()->validate([
            'group_id' => 'required|integer',
        ]);
        // chequear que el grupo exista
        $group = DB::table('table_groups')->where('id', request()->group_id)->first();
        if (!$group) {
            return response()->json(['errors' => [ 'group_id' => ['Group not found'] ] ], 422);
        }
        // eliminar el grupo
        DB::table('table_groups')->where('id', request()->group_id)->delete();
        return response()->json(['message' => 'Group deleted'], 200);
    }

    // Añadir una tabla a un grupo de tablas
    public function groupTableAdd() {
        // validar los datos
        request()->validate([
            'group_id' => 'required|integer',
            'table'    => 'required|string|max:255',
        ]);
        // chequear que la tabla exista
        $table = request()->table;
        // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $tables = DB::select('SHOW TABLES');
        $tables = array_map(function($table) {
            return $table->{'Tables_in_' . env('DB_DATABASE')};
        }, $tables);
        if (!in_array($table, $tables)) {
            return response()->json(['errors' => [ 'table' => ['Table not found'] ] ], 422);
        }
        // chequear que el grupo exista
        $group = DB::table('table_groups')->where('id', request()->group_id)->first();
        if (!$group) {
            return response()->json(['errors' => [ 'group_id' => ['Group not found'] ] ], 422);
        }
        // añadir la tabla al grupo
        $tables = json_decode($group->tables);
        if (is_null($tables)) $tables = [];

        if (!in_array($table, $tables)) {
            $tables[] = $table;
            DB::table('table_groups')->where('id', request()->group_id)->update([
                'tables' => json_encode($tables),
            ]);
            return response()->json(['message' => 'Table added to group'], 200);
        } 
    }

    // Actualizar un grupo de tablas
    public function groupTableRemove(Request $request) {
        $request->validate([
            'group_id' => 'required|integer',
            'table'    => 'required|string|max:255',
        ]);
        // chequear que la tabla exista
        $table = $request->table;
        // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $tables = DB::select('SHOW TABLES');
        $tables = array_map(function($table) {
            return $table->{'Tables_in_' . env('DB_DATABASE')};
        }, $tables);
        if (!in_array($table, $tables)) {
            return response()->json(['errors' => [ 'table' => ['Table not found'] ] ], 422);
        }
        // chequear que el grupo exista
        $group = DB::table('table_groups')->where('id', $request->group_id)->first();
        if (!$group) {
            return response()->json(['errors' => [ 'group_id' => ['Group not found'] ] ], 422);
        }
        // eliminar la tabla del grupo
        $tables = json_decode($group->tables);
        if (is_null($tables)) $tables = [];

        if (in_array($table, $tables)) {
            $tables = array_diff($tables, [$table]);
            $tables = array_values($tables);
            DB::table('table_groups')->where('id', $request->group_id)->update([
                'tables' => json_encode($tables),
            ]);
            return response()->json(['message' => 'Table removed from group'], 200);
        } 
    }

    // Generar un seeders para una tabla
    public function tableSeedersGenerate(Request $request) {
        $request->validate([
            'table' => 'required|string|max:255',
        ]);
        // chequear que la tabla exista
        $table = $request->table;
        // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $tables = DB::select('SHOW TABLES');
        $tables = array_map(function($table) {
            return $table->{'Tables_in_' . env('DB_DATABASE')};
        }, $tables);
        if (!in_array($table, $tables)) {
            return response()->json(['message' => 'Table not found'], 404);
        }
        // obtener los campos de la tabla
        // $columns = DB::getSchemaBuilder()->getColumnListing($table);
        
        // Obtener los datos de la tabla
        $data = DB::table($table)->get();
        $path = database_path('seeders/' . $table . '.json');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['message' => 'Seeder generated'], 200);
    }

    // Ejecutar un seeders para una tabla
    public function tableSeedersExecute(Request $request) {
        $request->validate([
            'table' => 'required|string|max:255',
        ]);
        // chequear que la tabla exista
        $table = $request->table;
        // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $tables = DB::select('SHOW TABLES');
        $tables = array_map(function($table) {
            return $table->{'Tables_in_' . env('DB_DATABASE')};
        }, $tables);
        if (!in_array($table, $tables)) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        $path = database_path('seeders/' . $table . '.json');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Seeder not found'], 404);
        }
        // iniciar una transacción
        DB::beginTransaction();

        try {
            

            // desactivar las restricciones de clave foránea
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // trunca la tabla
            // DB::table($table)->truncate();
            // No puedo porque no es compatrible con transacciones
            // Al parecer cuando se hace un truncate se hace un commit automático
            // y no se puede hacer commit o rollback dentro de una transacción
            // en su lugar se debe usar un delete
            DB::table($table)->delete();

            // restaura los datos
            $data = json_decode(file_get_contents($path), true);
            foreach ($data as $row) {
                DB::table($table)->insert($row);
            }

            // activar las restricciones de clave foránea
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Seeder restored'], 200);
        } catch (\Exception $e) {
            // cancelar la transacción
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }

    }
    // Generar un seeders para un grupo de tablas
    public function groupSeedersGenerate(Request $request) {
        $request->validate([
            'group_id' => 'required|integer',
        ]);
        // chequear que el grupo exista
        $group = DB::table('table_groups')->where('id', $request->group_id)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }
        // obtener las tablas del grupo
        $tables = json_decode($group->tables);
        if (is_null($tables)) $tables = [];
        
        $files = [];
        // iterar las tablas
        foreach ($tables as $table) {
            // chequear que la tabla exista
            // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
            $tables = DB::select('SHOW TABLES');
            $tables = array_map(function($table) {
                return $table->{'Tables_in_' . env('DB_DATABASE')};
            }, $tables);
            if (!in_array($table, $tables)) {
                return response()->json(['message' => 'Table not found'], 404);
            }
            // obtener los datos de la tabla
            $data = DB::table($table)->get();
            $path = database_path('seeders/' . $table . '.json');
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
            $files[] = $path;
        }
        return response()->json(['message' => 'Seeder generated', 'files' => $files], 200);
    }

    public function groupSeedersExecute(Request $request) {
        $request->validate([
            'group_id' => 'required|integer',
        ]);
        // chequear que el grupo exist
        $group = DB::table('table_groups')->where('id', $request->group_id)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }
        // obtener las tablas del grupo
        $tables = json_decode($group->tables);
        if (is_null($tables)) $tables = [];

        // iniciar una transacción
        DB::beginTransaction();

        // Desactivar las restricciones de clave foránea
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            
            // Iterar las tablas
            foreach ($tables as $key => $table) {
                
                // Chequear que la tabla exista
                // $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
                $tables = DB::select('SHOW TABLES');
                $tables = array_map(function($table) {
                    return $table->{'Tables_in_' . env('DB_DATABASE')};
                }, $tables);
                if (!in_array($table, $tables)) {
                    return response()->json(['message' => 'Table not found'], 404);
                }
    
                // Chequear que el seeder exista
                $path = database_path('seeders/' . $table . '.json');
                if (!file_exists($path)) {
                    return response()->json(['message' => 'Seeder not found'], 404);
                }
    
                // Trunca la tabla
                DB::table($table)->delete();
    
                // Restaura los datos
                $data = json_decode(file_get_contents($path), true);
                foreach ($data as $row) {
                    DB::table($table)->insert($row);
                }
    
            }
    
            // Activar las restricciones de clave foránea
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    
            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Seeder restored'], 200);
        } catch (\Throwable $th) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function backupGenerate(Request $request) {
        __dashboardTask(
            $title = "Generar backup de la base de datos",
            $description = 'Generación de un backup simple de la base de datos',
            $group = 'backup',
            $icon = null,
            $level = 'info',
            function () {
                $backup = new DatabaseBackup();
                $backup->generateBackupPerTable(
                    $filePath = storage_path('app/public/backup/database-'.time().'.sql'),
                    $ignoteDataTables = [
                        'failed_jobs',
                        'index_jobs',
                        'jobs',
                        'processed_jobs',
                        // 'shipment_tracks'
                    ]
                );
        
                // comprimir archivo en .zip
                $zip = new \ZipArchive();
                $zip->open($filePath.'.zip', \ZipArchive::CREATE);
                $zip->addFile($filePath, basename($filePath));
                $zip->close();
            
                return [
                    'status' => 'success',
                    'message' => 'Generado backup de la base de datos y limpiado de tablas de jobs',
                    'path' => $filePath,
                    'url' => url('storage/backup/'.basename($filePath)),
                ];        
            }
        );
    }

    public function listBackups() {
        $files = glob(storage_path('app/public/backup/*'));
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'path' => $file,
                'url' => url('storage/backup/'.basename($file)),
                // size in megabytes
                'size' => round(filesize($file) / 1024 / 1024, 2),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => pathinfo($file, PATHINFO_EXTENSION),
            ];
        }

        // ordenar los backups por fecha, del mas nuevo al mas antiguo
        usort($backups, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });
        return $backups;
    }

    public function backupDelete(Request $request) {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (file_exists($path)) {
            unlink($path);
            return response()->json(['message' => 'Backup deleted'], 200);
        }

        return response()->json(['message' => 'Backup not found'], 404);
    }
}