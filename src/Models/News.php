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
    protected $fillable = [
        'title',
        'description_short',
        'description',
        'image',
        'url',
        'icon',
        'featured',
        'user_id',
    ];
	protected $casts = [];
    protected $hidden = [
        'id',
    ];

    public static function boot() {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid    = (string) Str::uuid();
            $model->user_id = auth()->user()->id;
        });
    }

    public function getCreatedAtFormattedAttribute() {
        // 23/10/2023 12:00:00 PM
        return $this->created_at->format('d/m/Y h:i:s A');
    }
}