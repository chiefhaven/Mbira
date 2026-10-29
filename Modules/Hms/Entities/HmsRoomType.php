<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class HmsRoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function Rooms()
    {
        return $this->hasMany(HmsRoom::class);
    }
    public function Pricings()
    {
        return $this->hasMany(HmsRoomTypePricing::class);
    }
    
    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    /**
     * Get the project categories.
     */
    public function categories()
    {
        return $this->morphToMany(\App\Category::class, 'categorizable');
    }
}
