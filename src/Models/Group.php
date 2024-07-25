<?php
namespace AporteWeb\Dashboard\Models;

use Junges\ACL\Models\Group as JungesGroup;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Organization;

class Group extends JungesGroup {
    use SoftDeletes;

    protected $appends = ['organization_uuid', 'parent_uuid'];

    protected $fillable = [];
	protected $casts = [];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at', 'organization_id', 'parent_id', 'id'];

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
    public function organization() {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function getOrganizationUuidAttribute() {
        return $this->organization ? $this->organization->uuid : null;
    }

    public function getParentUuidAttribute() {
        return $this->parent ? $this->parent->uuid : null;
    }
    
}