<?php

namespace Modules\Cms\Entities;

use Illuminate\Database\Eloquent\Model;

class CmsSiteDetail extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $appends = ['logo_url', 'logo_path'];

    /**
     * Get display link for the media
     */
    public function getLogoUrlAttribute()
    {
        $url = null;
        $logo = CmsSiteDetail::getValue('logo');
        if (! empty($logo)) {
            $url = asset('/uploads/cms/'.rawurlencode($logo));
        }

        return $url;
    }

    /**
     * Get the feature image path.
     *
     * @return string
     */
    public function getLogoPathAttribute()
    {
        $logo_path = null;
        $logo = CmsSiteDetail::getValue('logo');
        if (! empty($logo)) {
            $logo_path = public_path('uploads').'/cms/'.$logo;
        }

        return $logo_path;
    }

    public static function createOrUpdateSiteDetails($site_details)
    {
        foreach ($site_details as $key => $site_detail) {
            CmsSiteDetail::updateOrCreate(['site_key' => $key], ['site_value' => json_encode($site_detail)]);
        }
    }

    /**
     * Return the value of the key
     *
     * @param $key string
     * @return mixed
     */
    public static function getValue($key, $jsonDecode = true)
    {
        $row = CmsSiteDetail::where('site_key', $key)
                ->first();

        if (! $jsonDecode) {
            return $row;
        }

        if (isset($row->site_value) && ! empty($row->site_value)) {
            return json_decode($row->site_value, true);
        }

        return null;
    }

    public static function getSiteDetails()
    {
        $site_details = CmsSiteDetail::pluck('site_value', 'site_key')->toArray();

        $details = [];
        if (! empty($site_details)) {
            foreach ($site_details as $site_key => $site_value) {
                $details[$site_key] = json_decode($site_value, true);
            }
        }

        return $details;
    }
}
