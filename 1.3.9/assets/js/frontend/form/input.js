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

    if (event.target.value == '' && event.target !== document.activeElement) {
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

function checkInputFile(event) {

    var input = event.target;

    var inputContainer = input.parentElement;
    var errorContainer = inputContainer.querySelector('.alert-error');

    var maxFile = input.dataset.wiMaxFile;
    var maxSize = input.dataset.wiMaxSize;
    var prettyMaxSize = ((maxSize/1024)/1024).toFixed(2); // MB

    var prettyErrorFile = "<i class='bi bi-exclamation-triangle'></i> Hai caricato troppi file! Puoi caricare massimo "+maxFile+" file";
    var prettyErrorSize = "<i class='bi bi-exclamation-triangle'></i> Hai caricato file troppo grandi! Le dimensioni massime sono di "+prettyMaxSize+"Mb";

    var files = input.files;

    inputContainer.classList.remove('input-error');
    errorContainer.innerHTML = '';

    for (var x in files) {

        if(files[x].size > maxSize){

            inputContainer.classList.add('input-error');
            errorContainer.innerHTML = prettyErrorSize;
            input.value = "";
            break;

        }

    }

    if (files.length > maxFile) {

        inputContainer.classList.add('input-error');
        errorContainer.innerHTML = prettyErrorFile;
        input.value = "";

    }
    
}

function setInput() {

    document.querySelectorAll("[data-wi-max-file]").forEach(element => {
        element.addEventListener("change", checkInputFile);
    });

    document.querySelectorAll("[data-wi-max-size]").forEach(element => {
        element.addEventListener("change", checkInputFile);
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

    document.querySelectorAll("[data-wi-search-input='true']").forEach(element => {
        element.addEventListener("keyup", showList);
        element.addEventListener("keyup", searchResults);
        element.addEventListener("change", controlList);
        element.addEventListener("focusout", hideList);
    });

    document.querySelectorAll("[data-wi-search-text='true']").forEach(element => {
        element.addEventListener("keyup", showList);
        element.addEventListener("keyup", searchText);
        element.addEventListener("focusout", hideList);
    });

    document.querySelectorAll("[data-wi-search-radio='true']").forEach(element => {
        element.addEventListener("keyup", showList);
        element.addEventListener("keyup", searchRadio);
        element.addEventListener("focusout", hideList);
    });

    document.querySelectorAll("[data-wi-list-value='true']").forEach(element => {
        element.addEventListener("click", checkInput);
        element.addEventListener("mousedown", checkInput);
    });

    document.querySelectorAll("[data-wi-phone='true']").forEach(element => {
        checkPhone(element);
    });

    check();
    setAutonumeric();

}