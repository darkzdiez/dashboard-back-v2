<?php

namespace AporteWeb\Dashboard\Models;

use Junges\ACL\Models\Permission as JungesPermission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends JungesPermission
{
    protected $appends = ['groups', 'organization_types'];

    public function getGroupsAttribute()
    {
        return $this->groups()->get(['name', 'uuid']);
    }

    public function getOrganizationTypesAttribute()
    {
        return $this->organizationTypes()->get(['name', 'uuid']);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(\AporteWeb\Dashboard\Models\Group::class, 'group_has_permissions', 'permission_id', 'group_id');
    }

    public function organizationTypes(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\OrganizationType::class, 'organization_type_has_permission', 'permission_id', 'organization_type_id');
    }
}
