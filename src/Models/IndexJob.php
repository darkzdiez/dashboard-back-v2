<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IndexJob extends Model {
    protected $table = 'index_jobs';
    protected $appends = [
        'created_at_formatted',
        'created_at_human',
        'time_execution_human',
        'memory_usage_human',
        'queries_time_human',
        'user_name'
    ];
    protected $fillable = [];
	protected $casts = [];
    protected $hidden = [
        'id',
        'user_id',
        'updated_at',
        'user_id',
        'user'
    ];

    public function getCreatedAtFormattedAttribute() {
        // 23/10/2023 12:00:00 PM
        return $this->created_at->format('d/m/Y h:i:s A');
    }

    public function getCreatedAtHumanAttribute() {
        return $this->created_at->diffForHumans();
    }

    public function user() {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    // tiempo de ejecución a horas minutos y segundos
    public function getTimeExecutionHumanAttribute() {
        // el tiempo de ejecución se guarda en segundos
        return $this->time_execution ? gmdate("H:i:s", $this->time_execution) : null;
    }

    // memoria usada en MB
    public function getMemoryUsageHumanAttribute() {
        return $this->memory_usage ? round($this->memory_usage / 1024 / 1024, 2) . ' MB' : null;
    }

    // tiempo de ejecución de las consultas a horas minutos y segundos
    public function getQueriesTimeHumanAttribute() {
        // el tiempo de ejecución se guarda en milisegundos
        return $this->queries_time ? gmdate("H:i:s", $this->queries_time / 1000) : null;
    }

    public function getUserNameAttribute() {
        return $this->user ? $this->user->name : 'System';
    }

}