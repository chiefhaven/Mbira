<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Return the value of the property
     *
     * @param $key string
     * @return mixed
     */
    public static function getProperty($key)
    {
        $row = System::where('key', $key)
            ->first();

        if (isset($row->value)) {
            return $row->value;
        } else {
            return null;
        }
    }

    /**
     * Return the value of the multiple properties
     *
     * @param $keys array
     * @return array
     */
    public static function getProperties($keys, $pluck = false)
    {
        if ($pluck == true) {
            return System::whereIn('key', $keys)
                ->pluck('value', 'key');
        } else {
            return System::whereIn('key', $keys)
                ->get()
                ->toArray();
        }
    }

    /**
     * Return the system default currency details
     *
     * @param void
     * @return object
     */
    public static function getCurrency()
    {
        $c_id = System::where('key', 'app_currency_id')
            ->first()
            ->value;

        $currency = Currency::find($c_id);

        return $currency;
    }

    /**
     * Set the property
     *
     * @param $key
     * @param $value
     *
     * @return void
     */
    public static function setProperty($key, $value)
    {
        System::where('key', $key)->update(['value' => $value]);
    }

    /**
     * Remove the specified property
     *
     * @param $key
     * @return void
     */
    public static function removeProperty($key)
    {
        System::where('key', $key)->delete();
    }

    /**
     * Add a new property, if exist update the value
     *
     * @param $key
     * @param $value
     * @return void
     */
    public static function addProperty($key, $value)
    {
        System::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    //Functions below are obtained from the support system

    /**
     * set and get count of ref_type,
     *
     * @param  $ref_type
     */
    public static function setAndGetReferenceCount($ref_type)
    {
        $system = System::where('key', $ref_type)->first();

        if (empty($system)) {
            $system = System::create(['key' => $ref_type, 'value' => 1]);
        } else {
            $system->value += 1;
            $system->save();
        }

        return $system->value;
    }

    /**
     * generate ref no. for $ref_type,
     *
     * @param  $ref_count, $ref_prefix
     */
    public static function generateReferenceNumber($ref_count, $ref_prefix = null)
    {
        $ref_digits =  str_pad($ref_count, 4, 0, STR_PAD_LEFT);
        $ref_number = $ref_prefix . $ref_digits;

        return $ref_number;
    }

    public static function createOrUpdateProperty($key, $value)
    {
        System::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getSignatureTags()
    {
        return  [
            'tags' => ['{name}', '{email}', '{role}'],
            'help_text' => __('messages.available_tags')
        ];
    }
}
