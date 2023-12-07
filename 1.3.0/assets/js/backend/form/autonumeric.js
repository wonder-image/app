var AUTONUMERIC_NUMBER = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    onInvalidPaste: 'truncate',
    outputFormat: '.',
    currencySymbolPlacement: 's'
};

var AUTONUMERIC_PRICE = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    onInvalidPaste: 'truncate',
    outputFormat: 'number',
    currencySymbol: '€',
    currencySymbolPlacement: 's'
};

var AUTONUMERIC_PERCENTIGE = {
    caretPositionOnFocus: 'end',
    decimalPlacesShownOnFocus: 2,
    digitGroupSeparator: '',
    onInvalidPaste: 'truncate',
    outputFormat: 'number',
    currencySymbol: '%',
    currencySymbolPlacement: 's'
};

function customAutonumeric(element, autonumeric) {

    var option = {};

    if (autonumeric.caretPositionOnFocus != undefined) { 
        option.caretPositionOnFocus = autonumeric.caretPositionOnFocus; 
    }

    if (element.dataset.wiNumberDecimal != undefined) { 
        option.decimalPlacesShownOnFocus = element.dataset.wiNumberDecimal;
        option.decimalPlaces = element.dataset.wiNumberDecimal;
    } else {
        if (autonumeric.decimalPlacesShownOnFocus != undefined) {
            option.decimalPlacesShownOnFocus = autonumeric.decimalPlacesShownOnFocus;
        }
    }

    if (element.dataset.wiNumberGroupSeparator != undefined) { 
        option.digitGroupSeparator = element.dataset.wiNumberGroupSeparator;
    } else {
        if (autonumeric.digitGroupSeparator != undefined) {
            option.digitGroupSeparator = autonumeric.digitGroupSeparator;
        }
    }

    if (autonumeric.onInvalidPaste != undefined) { 
        option.onInvalidPaste = autonumeric.onInvalidPaste; 
    }

    if (autonumeric.outputFormat != undefined) { 
        option.outputFormat = autonumeric.outputFormat; 
    }

    if (element.dataset.wiNumberSymbol != undefined) { 
        option.currencySymbol = element.dataset.wiNumberSymbol;
    } else {
        if (autonumeric.currencySymbol != undefined) {
            option.currencySymbol = autonumeric.currencySymbol;
        }
    }

    if (element.dataset.wiNumberSymbolPlacement != undefined) { 
        option.currencySymbolPlacement = element.dataset.wiNumberSymbolPlacement;
    } else {
        if (autonumeric.currencySymbolPlacement != undefined) {
            option.currencySymbolPlacement = autonumeric.currencySymbolPlacement;
        }
    }

    return option;
    
}

function setAutonumeric() {
            
    document.querySelectorAll("[data-wi-number='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        
        if (el === null) {

            new AutoNumeric(element, customAutonumeric(element, AUTONUMERIC_NUMBER)); 
        
        }

    });

    document.querySelectorAll("[data-wi-price='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        if (el === null) { new AutoNumeric(element, customAutonumeric(element, AUTONUMERIC_PRICE)); }

    });
    
    document.querySelectorAll("[data-wi-percentige='true']").forEach(element => {
        
        var el = AutoNumeric.getAutoNumericElement(element);
        if (el === null) { new AutoNumeric(element, customAutonumeric(element, AUTONUMERIC_PERCENTIGE)); }

    });

}