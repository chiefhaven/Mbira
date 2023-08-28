<?php

namespace Modules\Cms\Entities;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['slug', 'feature_image_url'];

    /**
     * Get the slug for post.
     *
     * @return string
     */
    public function getSlugAttribute()
    {
        return strtolower(str_replace(' ', '-', $this->title));
    }

    /**
     * Get the business that owns the user.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by', 'id');
    }

    /**
     * Get the feature image.
     *
     * @return string
     */
    public function getFeatureImageUrlAttribute()
    {
        $image_url = null;

        if (! empty($this->feature_image)) {
            $image_url = asset('/uploads/cms/'.rawurlencode($this->feature_image));
        }

        return $image_url;
    }

    /**
     * Get the feature image path.
     *
     * @return string
     */
    public function getFeatureImagePathAttribute()
    {
        $image_path = null;

        if (! empty($this->feature_image)) {
            $image_path = public_path('uploads').'/cms/'.$this->feature_image;
        }

        return $image_path;
    }

    /**
     * Get the meta for the page.
     */
    public function pageMeta()
    {
        return $this->hasMany('Modules\Cms\Entities\CmsPageMeta', 'cms_page_id', 'id');
    }

    public static function getEnabledPages($type = 'page')
    {
        $pages = CmsPage::where('type', $type)
                    ->whereNull('layout')
                    ->orderBy('priority', 'asc')
                    ->where('is_enabled', 1)
                    ->get();

        return $pages;
    }

    public static function getPagesCount($type = '')
    {
        if (empty($type)) {
            return 0;
        }

        $pages_count = CmsPage::where('type', $type)
                        ->where('is_enabled', 1)
                        ->count();

        return $pages_count;
    }
}
