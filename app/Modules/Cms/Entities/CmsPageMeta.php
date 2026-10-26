<?php

namespace Modules\Cms\Entities;

use Illuminate\Database\Eloquent\Model;

class CmsPageMeta extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the page for the meta.
     */
    public function page()
    {
        return $this->belongsTo('Modules\Cms\Entities\CmsPage', 'cms_page_id');
    }

    /**
     * Update or create meta data for given page
     */
    public static function updateOrCreateMetaForPage($metas, $page_id)
    {
        if (! empty($metas) && ! empty($page_id)) {
            foreach ($metas as $meta_key => $meta_value) {
                CmsPageMeta::updateOrCreate(
                    ['id' => $meta_value['id'], 'cms_page_id' => $page_id, 'meta_key' => $meta_key],
                    ['meta_value' => json_encode($meta_value)]
                );
            }
        }
    }
}
