function check() {
    
    document.querySelectorAll("form button[type='submit']").forEach(button => {
 
        button.removeAttribute("disabled");

        var inputList = button.form.elements;

        for (let i = 0; i < inputList.length; i++) {

            var input = inputList[i];

            if (input.required) {
                if (input.type == 'checkbox' || input.type == 'radio') {
                    if (input.checked == false) { button.setAttribute("disabled", null); }
                } else {
                    if (input.value == "") { button.setAttribute("disabled", null); }
                }
            }

        }

        var formElement = button.form;

        formElement.querySelectorAll(".wi-checkbox-required, .wi-radio-required").forEach(checkboxContainer => {

            if (checkboxContainer.querySelectorAll("input[type='checkbox']:checked, input[type='radio']:checked").length == 0) { button.setAttribute("disabled", null); }

        });

    });

}

function lengthCount(event) {

    var val = event.target.value;
    var container =  event.target.parentElement;

    if (container.querySelector('.wi-counter')) {
        container.querySelector('.wi-counter').innerHTML = val.length;
    }

}

function checkColor(event) {

    var val = event.target.value;
    var container =  event.target.parentElement;

    if (val.includes('#')) {
        container.querySelector('.wi-show-color').style.color = val;
    } else {
        container.querySelector('.wi-show-color').el.style.color = 'rgba('+val+')';
    }
    
}

function generateCode(selector) {

    document.querySelector(selector).value = code();
    
}

function disableInput(type) {

    if (type == 'user') {
        var inputArray = document.querySelectorAll('input[name=name], input[name=surname], input[name=username], input[name=email], select[name=active]');
    }

    inputArray.forEach(element => {

        element.disabled = true;
        element.dataset.wiOldValue = element.value;
        element.dataset.wiOldRequired = element.required;
        
        element.value  = null;
        element.required = false;

    }); 

}

function enabledInput(type) {

    if (type == 'user') {
        var inputArray = document.querySelectorAll('input[name=name], input[name=surname], input[name=username], input[name=email], select[name=active]'); 
    }

    inputArray.forEach(element => {

        element.disabled = false;
        element.value = element.dataset.wiOldValue;
        element.required = element.dataset.wiOldRequired;

    }); 
    
}

function setDynamicSearch(element) {

    var dataValue = element.dataset.wiValue;
    var containerMaster = element.parentElement;
    var container = containerMaster.querySelector('.card .card-body');
    var footer = containerMaster.querySelector('.card .card-footer');

    if (dataValue != '') {

        var value = dataValue;
        var url = element.dataset.wiSearchUrl;

        $.ajax({
            type: "POST",
            url: url,
            data: { 
                post: 'true',
                id: value
            }, 
            success: function (data) {
                createCheckbox(data, element, container, true);
                checkInput();
            },
            error: function (XMLHttpRequest) {

                ajaxRequestError(XMLHttpRequest);
                footer.innerHTML = 'Errore!';

            }
        });
        
    }

}

function createCheckbox(array, element, container, checked = false) {

    var name = element.dataset.wiName;

    if (element.dataset.wiSearchRadio != undefined) {
        var type = "radio";
    } else if (element.dataset.wiSearchCheckbox != undefined) {
        var type = "checkbox";
    }

    var containerMaster = container.parentElement;
    var footer = containerMaster.querySelector('.card .card-footer');
    footer.innerHTML = 'Cerco...';

    const response = JSON.parse(array);
    const nResponse = response.length;

    var HTML_CHECKED = "";

    container.querySelectorAll('input:checked').forEach(el => {

        el.setAttribute('checked', true);
        HTML_CHECKED += "<div class='form-check'>"+el.parentElement.innerHTML+"</div>";

    });

    container.innerHTML = "";

    for (let index = 0; index < nResponse; index++) {

        var idCode = code();
        var HTML = "";
        var att = "";

        var value = response[index]['value'];
        var label = response[index]['label'];
        var inputValue = response[index]['input-value'];

        if (checked) { var att = "checked"; }

        if (HTML_CHECKED.search('value="'+value+'"') == '-1') {

            HTML += "<div class='form-check'>";
            HTML += "<input id='"+idCode+"' type='"+type+"' class='form-check-input' name='"+name+"' value='"+value+"' data-wi-check='true' "+att+" >";
            HTML += "<label class='form-check-label wi-check-label' for='"+idCode+"'>"+label+"</label>";
            HTML += "</div>";

            container.insertAdjacentHTML("beforeend", HTML);

        }

    }

    container.insertAdjacentHTML("beforeend", HTML_CHECKED);

    if (inputValue == '') {
        footer.innerHTML = "Cerca risultati";
    } else if (nResponse == 1) {
        footer.innerHTML = nResponse+" risultato";
    } else if (nResponse != 1) {
        footer.innerHTML = nResponse+" risultati";
    }

}

function inputSearch(event) {

    var inputText = event.target;

    var containerMaster = inputText.parentElement;
    var container = containerMaster.querySelector('.card .card-body');
    var footer = containerMaster.querySelector('.card .card-footer');

    var inputValue = inputText.value.toLowerCase();

    if (inputText.dataset.wiSearchUrl != undefined) {

        var url = inputText.dataset.wiSearchUrl;

        var footer = containerMaster.querySelector('.card .card-footer');
        footer.innerHTML = 'Cerco...';

        $.ajax({
            type: "POST",
            url: url,
            data: { 
                post: 'true',
                search: inputValue
            }, 
            success: function (data) {
                createCheckbox(data, inputText, container);
                checkInput();
            },
            error: function (XMLHttpRequest) {

                ajaxRequestError(XMLHttpRequest);
                footer.innerHTML = 'Errore! Riprova la ricerca.';

            }
        });
        
    } else {
                        
        container.querySelectorAll('label.wi-check-label').forEach(element => {
    
            var container = element.parentElement;
            var name = element.innerHTML.toLowerCase();
    
            if (name.includes(inputValue)) {
                element.parentElement.style.display = 'block';
            } else if (container.querySelector('input').checked == false) {
                element.parentElement.style.display = 'none';
            }
    
        });

    }
    
}