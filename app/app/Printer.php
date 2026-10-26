<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public static function capability_profiles()
    {
        $profiles = [
            'default' => __('lang_v1.default'),
            'simple' => __('lang_v1.simple'),
            'SP2000' => __('lang_v1.star_branded'),
            'TEP-200M' => __('lang_v1.espon_tep'),
            'P822D' => 'P822D',
        ];

        return $profiles;
    }

    public static function capability_profile_srt($profile)
    {
        $profiles = Printer::capability_profiles();

        return isset($profiles[$profile]) ? $profiles[$profile] : '';
    }

    public static function connection_types()
    {
        $types = [
            'network' => __('lang_v1.network'),
            'windows' => __('lang_v1.windows'),
            'linux' => __('lang_v1.linux'),
        ];

        return $types;
    }

    public static function connection_type_str($type)
    {
        $types = Printer::connection_types();

        return isset($types[$type]) ? $types[$type] : '';
    }

    /**
     * Return list of printers for a business
     *
     * @param  int  $business_id
     * @param  bool  $show_select = true
     * @return array
     */
    public static function forDropdown($business_id, $show_select = true)
    {
        $query = Printer::where('business_id', $business_id);

        $printers = $query->pluck('name', 'id');
        if ($show_select) {
            $printers->prepend(__('messages.please_select'), '');
        }

        return $printers;
    }
}
