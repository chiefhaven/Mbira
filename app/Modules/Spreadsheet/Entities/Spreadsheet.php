<?php

namespace Modules\Spreadsheet\Entities;

use Illuminate\Database\Eloquent\Model;

class Spreadsheet extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sheet_data' => 'array',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sheet_spreadsheets';

    /**
     * user who created a sheet.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function shares()
    {
        return $this->hasMany(\Modules\Spreadsheet\Entities\SpreadsheetShare::class, 'sheet_spreadsheet_id');
    }
}
