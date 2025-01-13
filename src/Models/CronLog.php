<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CronLog extends Model {
    protected $table = 'cron_log';
    protected $appends = [
        'run_at_formatted',
        'run_at_human',
    ];

    protected $fillable = [];
	protected $casts = [
        'run_at' => 'datetime',
    ];

    public function cron() {
        return $this->belongsTo(Cron::class, 'cron_id', 'id');
    }

    public function getRunAtFormattedAttribute() {
        // Martes 23 de Octubre de 2023 a las 12:00 pm
        return $this->run_at->format('l d \d\e F \d\e Y \a \l\a\s h:i a');
    }

    public function getRunAtHumanAttribute() {
        return $this->run_at->diffForHumans();
    }
}