<?php
namespace AporteWeb\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\User;

class Note extends Model {
    use SoftDeletes;

	protected $table = 'notes';
    protected $appends = ['created_at_human', 'created_at_formatted'];

    protected $fillable = [
        'area',
        'refid',
        'title',
        'content',
        'user_id',
        'level',
        'type'
    ];
	protected $casts = [];

    public static function boot() {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function getCreatedAtHumanAttribute() {
        return $this->created_at->diffForHumans();
    }

    public function getCreatedAtFormattedAttribute() {
        return $this->created_at->format('d/m/Y h:i A');
    }
}