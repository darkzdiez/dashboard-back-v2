<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GroupNotifications extends Model {
	protected $table = 'group_notifications';

    protected $fillable = [
        'key',
        'group_id'
    ];
	protected $casts = [];

    public static function boot() {
        parent::boot();
    }
}