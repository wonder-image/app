function checkPhone(element) {
    
    var phones = [
        { "mask": "### ### ####"}
    ];

    $(element).inputmask({ 
        mask: phones, 
        greedy: false, 
        placeholder: '', 
        definitions: { 
            '#': { 
                validator: "[0-9]", 
                cardinality: 1
            }
        } 
    });

}
