function capitalize_first_letter(string) {
    let capitalized = "";
    try {
        capitalized = string.charAt(0).toUpperCase() + string.slice(1);
    } catch (error) {
        console.error(`${error}`)
        console.error(string);
    }
    return capitalized.replace(/_/g, " ");
}

function number_format(number) {
    return Intl.NumberFormat().format(number);
}

function calculate_toan_term_in_days(loan_term, repayment_frequency_type) {
    switch (repayment_frequency_type) {
        case 'days':
            return loan_term;
        case 'weeks':
            return loan_term * 7;
        case 'months':
            return loan_term * 30;
        case 'years':
            return loan_term * 365;
        default:
            return loan_term;
    }
}