<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Country;

class KycIdentification extends Model
{
    /*
     * Table Name
     */
    protected $table = 'kyc_identification';

    /*
     * Status Data
     */
    const KYC_STATUS = ['pending', 'approved', 'rejected'];

    const DOCUMENT_TYPES = [
        'passport',
        'national_id_front',
        'national_id_back',
        'driver_license',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contact_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'dob',
        'gender',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'country_id',
        //Everything below is missing from the validation
        'notes',
        'reviewed_by',
        'reviewed_at',
        'status',
    ];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getNationalityAttribute()
    {
        return $this->country->name;
    }

    /**
     * Get the country associated with the KycIdentification
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function country()
    {
        return $this->belongsTo(Country::class)->withDefault([
            'name' => trans('lang_v1.no_country_chosen')
        ]);
    }

    /**
     *
     * Relation with approver
     *
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    public function checker_info()
    {
        return $this->belongsTo(\App\User::class, 'reviewed_by', 'id');
    }

    public function media()
    {
        return $this->morphMany(\Modules\Accounting\Entities\Media::class, 'model');
    }

    public static function uploadDocuments(Request $request, KycIdentification $kyc_identification)
    {
        $business_id = session('business.id');
        $document_types = KycIdentification::DOCUMENT_TYPES;

        foreach ($document_types as $document_type) {
            if ($request->has($document_type)) {
                //Replaces the underscore with a hypen in the document_type
                $request->description = str_replace('_', '-', $document_type);
                Media::uploadMedia($business_id, $kyc_identification, $request, $document_type, false);
            }
        }
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case 'pending':
                return '<span class="label label-warning">' . trans('accounting::core.pending') . '</span>';

            case 'approved':
                return '<span class="label label-success">' . trans('accounting::core.approved') . '</span>';

                //rejected
            default:
                return '<span class="label label-danger">' . trans('loan::general.rejected') . '</span>';
        }
    }
}
