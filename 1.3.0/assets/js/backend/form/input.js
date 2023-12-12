function check() {
    
    document.querySelectorAll("form button[type='submit']").forEach(button => {
 
        button.removeAttribute("disabled");

        var inputList = button.form.elements;

        for (let i = 0; i < inputList.length; i++) {

            var input = inputList[i];

            if (input.required) {
                if (input.value == "") {
                    button.setAttribute("disabled", null);
                }
            }

        }

    })

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

function inputSearch(event) {

    var inputText = event.target;

    var containerMaster = inputText.parentElement;
    var container = containerMaster.querySelector('.card .card-body');

    var value = inputText.value.toLowerCase();

    if (inputText.dataset.wiSearchUrl != undefined) {

        var url = inputText.dataset.wiSearchUrl;

        if (inputText.dataset.wiSearchRadio != undefined) {
            var type = "radio";
        } else if (inputText.dataset.wiSearchCheckbox != undefined) {
            var type = "checkbox";
        }

        var footer = containerMaster.querySelector('.card .card-footer');
        footer.innerHTML = 'Cerco...';

        $.ajax({
            type: "POST",
            url: url,
            data: { 
                post: 'true',
                search: value
            }, 
            success: function (data) {

                const response = JSON.parse(data);
                const nResponse = response.length;
                
                var listHTML = "";

                for (let index = 0; index < nResponse; index++) {

                    var code = code();

                    const value = response[index]['value'];
                    const label = response[index]['label'];
                    const inputValue = response[index]['input-value'];

                    listHTML += "<div class='form-check'><input id='"+code+"' type='"+type+"' class='form-check-input' value='"+value+"' data-wi-check='true'><label class='form-check-label wi-check-label' for='"+code+"'>"+label+"</label></div>";

                }

                if (inputValue == '') {
                    footer.innerHTML = "Cerca risultati";
                } else if (nResponse == 1) {
                    footer.innerHTML = nResponse+" risultato";
                } else if (nResponse != 1) {
                    footer.innerHTML = nResponse+" risultati";
                }

                container.innerHTML = listHTML;

                

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
    
            if (name.includes(value)) {
                element.parentElement.style.display = 'block';
            } else if (container.querySelector('input').checked == false) {
                element.parentElement.style.display = 'none';
            }
    
        });

    }
    
}