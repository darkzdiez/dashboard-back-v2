<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cron extends Model {
    use SoftDeletes;

    protected $table = 'cron';
    protected $appends = [];

    protected $fillable = [];
	protected $casts = [];

    public static function boot() {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function logs() {
        return $this->hasMany(CronLog::class, 'cron_id', 'id')->orderBy('id', 'desc');
    }
}