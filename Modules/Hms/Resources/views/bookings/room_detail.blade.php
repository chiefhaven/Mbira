@if ($is_edit)
    <tr>
@endif
        <input type="hidden" value="{{ $data['total_price'] }}" name="rooms[{{ $currentIndex }}][total_price]">
        <input type="hidden" value="{{ $data['price'] }}" name="rooms[{{ $currentIndex }}][price]">
        <input type="hidden" value="{{ $type->id }}" name="rooms[{{ $currentIndex }}][type_id]">
        <input type="hidden" class="room-id-input" value="{{ $room->id }}" name="rooms[{{ $currentIndex }}][room_id]">
        <input type="hidden" value="{{ $data['no_of_adult'] }}" name="rooms[{{ $currentIndex }}][no_of_adult]">
        <input type="hidden" value="{{ $data['no_of_child'] }}" name="rooms[{{ $currentIndex }}][no_of_child]">
    <td>
        {{ $type->type }}
       
    </td>
    <td>
        {{ $room->room_number }}
    </td>
    <td>
        {{ $data['no_of_adult'] }}
    </td>
    <td>
        {{ $data['no_of_child'] }}
    </td>
    <td class="price-td display_currency" data-currency_symbol="true">
        {{ $data['total_price'] }}
    </td>
    <td> 
        <button type="button" class="btn btn-danger btn-sm remove"><i class="fas fa-trash-alt"></i></button> 
        <button type="button" class="btn btn-primary btn-sm edit"><i class="fas fa-edit"></i></button> 
    </td> 
 @if ($is_edit)
</tr>
@endif