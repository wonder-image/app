const AUTONUMERIC_NUMBER = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    outputFormat: '.'
};

const AUTONUMERIC_PRICE = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    onInvalidPaste: 'truncate',
    outputFormat: 'number',
    currencySymbol: '€',
    currencySymbolPlacement: 's'
};

const AUTONUMERIC_PERCENTIGE = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    onInvalidPaste: 'truncate',
    outputFormat: 'number',
    currencySymbol: '%',
    currencySymbolPlacement: 's'
};

function setAutonumeric() {
            
    document.querySelectorAll("[data-wi-number='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        if (el === null) {new AutoNumeric(element, AUTONUMERIC_NUMBER); }

    });

    document.querySelectorAll("[data-wi-price='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        if (el === null) { new AutoNumeric(element, AUTONUMERIC_PRICE); }

    });
    
    document.querySelectorAll("[data-wi-percentige='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        if (el === null) { new AutoNumeric(element, AUTONUMERIC_PERCENTIGE); }

    });

}