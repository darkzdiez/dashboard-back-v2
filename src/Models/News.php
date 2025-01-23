<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class News extends Model {
    protected $table = 'news';
    protected $appends = [
        'created_at_formatted',
    ];
    protected $fillable = [];
	protected $casts = [];
    protected $hidden = [
        'id',
    ];

    public function getCreatedAtFormattedAttribute() {
        // 23/10/2023 12:00:00 PM
        return $this->created_at->format('d/m/Y h:i:s A');
    }
}