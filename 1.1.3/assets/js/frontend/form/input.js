function check() {
    
    document.querySelectorAll("form .wi-submit").forEach(button => {
 
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

    })

}

function togglePassword(el, id) {

    var input = document.getElementById(id);

    if (input.type == 'password') {
        input.type = 'text'
        el.classList.remove('bi-eye');
        el.classList.add('bi-eye-slash');
    }else{
        input.type = 'password'
        el.classList.remove('bi-eye-slash');
        el.classList.add('bi-eye');
    }

}

function checkNumber(event) {

    event.target.value = event.target.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
    
}

function labelPositionTop(event) {

    event.target.parentElement.classList.add('compiled');

}

function labelPosition(event) {

    var container = event.target.parentElement;

    if (event.target.value == '') {
        container.classList.remove('compiled');
    } else {
        container.classList.add('compiled');
    }

}

function customDateRange(element) {

    var inputFrom = element.querySelector('input.wi-daterange-from');
    var inputTo = element.querySelector('input.wi-daterange-to');

    if (inputFrom.value != '') {
        $('#'+inputTo.id).datepicker('option', 'minDate', inputFrom.value);
    }

}

function customDateTimeRange(element) {

    var inputFrom = element.querySelector('input.wi-datetimerange-from');
    var inputTo = element.querySelector('input.wi-datetimerange-to');

    if (inputFrom.value != '') {
        $('#'+inputTo.id).datetimepicker('option', 'minDate', inputFrom.value);
    }

}

function checkLabel() {

    document.querySelectorAll("[data-wi-label='true']").forEach(element => {
        
        var container = element.parentElement;

        if (element.value == '') {
            container.classList.remove('compiled');
        } else {
            container.classList.add('compiled');
        }
        
    });

}

function setInput() {
    
    document.querySelectorAll("[data-wi-number='true']").forEach(element => {
        element.addEventListener("input", checkNumber);
    });

    document.querySelectorAll("[data-wi-label='true']").forEach(element => {
        element.addEventListener("input", labelPosition);
        element.addEventListener("change", labelPosition);
        element.addEventListener("focusin", labelPositionTop);
        element.addEventListener("focusout", labelPosition);
    });

    document.querySelectorAll("[data-wi-check='true']").forEach(element => {
        element.addEventListener("keyup", check);
        element.addEventListener("change", check);
        element.addEventListener("focusin", check);
        element.addEventListener("focusout", check);
    });

    document.querySelectorAll("[data-wi-list-input='true']").forEach(element => {
        element.addEventListener("keyup", searchInput);
        element.addEventListener("focusin", searchInput);
        element.addEventListener("keyup", showList);
        element.addEventListener("focusout", hideList);
        element.addEventListener("change", controlList);
        element.addEventListener("focusout", controlList);
    });

    document.querySelectorAll("[data-wi-list-value='true']").forEach(element => {
        element.addEventListener("click", checkInput);
        element.addEventListener("mousedown", checkInput);
    });

    check();

}