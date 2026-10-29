<?php

namespace Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Modules\Accounting\Entities\ChartOfAccount;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountingTableExport implements FromQuery, WithHeadings
{
    
    public function query()
    {
        return ChartOfAccount::query()
        ->select('name', 'gl_code', 'account_type',  'active', 'allow_manual');
    }
    
    public function headings(): array
    {
        return [
            'Name',
            'GL Code',
            'Account Type',
            'Active',
            'Manual Entries Allowed'
        ];
    }
}