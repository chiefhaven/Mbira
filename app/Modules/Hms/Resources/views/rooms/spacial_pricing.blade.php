<tr>
    <td>
        <select class="form-control" required name="pricing[{{ $currentIndex }}][adults]">
            @for ($i = 1; $i <= $room_type->no_of_adult; $i++)
                <option value="{{ $i }}">{{ $i }}</option>
            @endfor
        </select>
    </td> 
    <td>
        <select class="form-control" required name="pricing[{{ $currentIndex }}][childrens]">
            @for ($i = 0; $i <= $room_type->no_of_child; $i++)
                <option value="{{ $i }}">{{ $i }}</option>
            @endfor
        </select>
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][monday]" type="number">
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][tuesday]" type="number">
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][wednesday]" type="number">
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][thursday]" type="number">
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][friday]" type="number"></td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][saturday]" type="number">
    </td> 
    <td>
        <input class="form-control" required step="0.01" name="pricing[{{ $currentIndex }}][sunday]" type="number">
    </td> 
    <td> 
        <button type="button" class="btn btn-danger remove"><i class="fas fa-trash-alt"></i></button> 
        <button type="button" class="btn btn-success copy"><i class="fas fa-copy"></i></button> 
    </td> 
</tr>