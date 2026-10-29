$(document).ready(function() {
    // ZATCA address validation - simple and standard
    var addressFields = ['street_name', 'building_number', 'city', 'zip_code', 'additional_number', 'state', 'country'];
    
    function updateValidation() {
        var hasTax = $('#tax_number').val().trim() !== '';
        
        addressFields.forEach(function(field) {
            var input = $('#' + field);
            if (input.length) {
                if (hasTax) {
                    input.attr('required', true);
                } else {
                    input.removeAttr('required');
                }
            }
        });
    }
    
    // ZATCA name length validation: max 127 characters (real-time check)
    function checkNameLength() {
        var contactTypeRadio = $('input[name="contact_type_radio"]:checked').val();
        if (contactTypeRadio === 'individual') {
            var prefix = $('#prefix').val() || '';
            var firstName = $('#first_name').val() || '';
            var middleName = $('#middle_name').val() || '';
            var lastName = $('#last_name').val() || '';
            
            var nameArray = [];
            if (prefix.trim() !== '') nameArray.push(prefix.trim());
            if (firstName.trim() !== '') nameArray.push(firstName.trim());
            if (middleName.trim() !== '') nameArray.push(middleName.trim());
            if (lastName.trim() !== '') nameArray.push(lastName.trim());
            
            var fullName = nameArray.join(' ');
            var totalLength = fullName.length;
            
            return totalLength;
        }
        return 0;
    }
    
    // Prevent typing if total length exceeds 127 characters
    function handleNameFieldKeyPress(e) {
        var currentLength = checkNameLength();
        
        // Allow backspace (8), delete (46), and other control keys
        if (e.which === 8 || e.which === 46 || e.which === 0 || e.ctrlKey || e.metaKey) {
            return true;
        }
        
        // If already at or above 127, prevent typing
        if (currentLength >= 127) {
            e.preventDefault();
            swal({
                title: LANG.error || 'Error',
                text: LANG.name_length_exceeded.replace(':length', currentLength),
                icon: 'error',
                button: LANG.ok || 'OK'
            });
            return false;
        }
    } 
    // Attach keypress and paste event listeners to name fields
    $(document).on('keypress', '#prefix, #first_name, #middle_name, #last_name', handleNameFieldKeyPress);
    
    // Apply on modal show
    $(document).on('shown.bs.modal', '.contact_modal', function() {
        updateValidation();
    });
});
