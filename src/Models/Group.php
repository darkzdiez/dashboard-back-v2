<?php
namespace AporteWeb\Dashboard\Models;

use Junges\ACL\Models\Group as JungesGroup;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends JungesGroup {
    use SoftDeletes;

    protected $appends = [];

    protected $fillable = [];
	protected $casts = [];

    public static function boot() {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function parent() {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function notifications() {
        return $this->hasMany(GroupNotifications::class, 'group_id');
    }
}