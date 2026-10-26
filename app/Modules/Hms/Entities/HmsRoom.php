<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class HmsRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function type()
    {
        return $this->belongsTo(HmsRoomType::class);
    }

    public static function non_booking_rooms($type_id, $arrival_date_time, $departure_date_time, $existing_rooms, $adate, $ddate)
    {
        
$non_booking_rooms = HmsRoom::leftJoin('hms_booking_lines', function ($join) use ($arrival_date_time, $departure_date_time) {
    $join->on('hms_rooms.id', '=', 'hms_booking_lines.hms_room_id')
         ->leftJoin('transactions', 'hms_booking_lines.transaction_id', '=', 'transactions.id')
         ->where(function ($query) use ($arrival_date_time, $departure_date_time) {
             $query->whereRaw("transactions.hms_booking_arrival_date_time <= ? AND transactions.hms_booking_departure_date_time >= ?", [$departure_date_time, $arrival_date_time])
                 ->orWhereRaw("transactions.hms_booking_arrival_date_time BETWEEN ? AND ? OR transactions.hms_booking_departure_date_time BETWEEN ? AND ?", [$arrival_date_time, $departure_date_time, $arrival_date_time, $departure_date_time])
                 ->orWhere(function ($query) use ($arrival_date_time, $departure_date_time) {
                     $query->whereRaw("transactions.hms_booking_arrival_date_time <= ? AND transactions.hms_booking_departure_date_time >= ?", [$arrival_date_time, $departure_date_time]);
                 });
         })
         ->orWhereNull('hms_booking_lines.hms_room_id');
        })
        ->leftJoin('hms_room_unavailables', function ($join) use ($adate, $ddate) {
            $join->on('hms_rooms.id', '=', 'hms_room_unavailables.hms_rooms_id')
                 ->where(function ($query) use ($adate, $ddate) {
                     $query->whereDate('hms_room_unavailables.date_to', '>=', $adate)
                           ->whereDate('hms_room_unavailables.date_from', '<=', $ddate)
                           ->orWhere(function ($query) use ($adate) {
                               $query->whereDate('hms_room_unavailables.date_to', '=', $adate)
                                     ->whereTime('hms_room_unavailables.date_to', '>', $adate);
                           })
                           ->orWhere(function ($query) use ($ddate) {
                               $query->whereDate('hms_room_unavailables.date_from', '=', $ddate)
                                     ->whereTime('hms_room_unavailables.date_from', '<', $ddate);
                           });
                 });
        })
        ->where('hms_rooms.hms_room_type_id', $type_id)
        ->whereNotIn('hms_rooms.id', $existing_rooms)
        ->whereNull('hms_booking_lines.id')
        ->whereNull('hms_room_unavailables.id')
        ->select('hms_rooms.*')
        ->pluck('room_number', 'id')
        ->toArray();
        
        return $non_booking_rooms;
    }
}
