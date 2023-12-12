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

    var container = event.target.parentElement;
    var value = event.target.value.toLowerCase();
                    
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