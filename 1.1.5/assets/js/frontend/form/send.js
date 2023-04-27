function formToArray(formElements) {

    const ARRAY = {};

    for (let i = 0; i < formElements.length; i++) {

        var add = false;

        var input = formInput[i];

        if (input.type == 'checkbox' || input.type == 'radio') {
            if (input.checked == true) { var add = true; }
        } else {
            if (input.value != "") { var add = true;}
        }

        if (add) {

            var inputName = input.name;
            var inputValue = input.value;

            if (inputName.includes("[]")) {

                inputName = inputName.replace("[]", "");

                if (inputName in ARRAY) {
                    inputName = inputName.replace("[]", "");
                    ARRAY[inputName].push(inputValue);
                } else {
                    inputName = inputName.replace("[]", "");
                    ARRAY[inputName] = [];
                    ARRAY[inputName].push(inputValue);
                }
                
            } else {

                if (inputName in ARRAY) {

                } else {
                    ARRAY[inputName] = inputValue;
                }

            }

        }

    }
    
}

function prettyFormResponse(data) {

    if (data != '') {

        var container = document.querySelector("#loading-spinner .center");
        container.classList.add("w-80");
        container.innerHTML = '<div class="title-big a-c"><i class="bi bi-x-circle tx-danger"></i></div><div class="subtitle mt-8 a-c">C\'è stato un problema con l\'invio della tua richiesta</div><div class="c-w mt-10"><a onclick="location.reload();" class="btn btn-primary c-w">Riprova</a></div>';

    } else {
        
        var container = document.querySelector("#loading-spinner .center");
        container.classList.add("w-80");
        container.innerHTML = '<div class="title-big a-c"><i class="bi bi-check2-circle tx-success"></i></div><div class="subtitle mt-8 a-c">La tua richiesta è stata inviata con successo!</div><div class="c-w mt-10"><a onclick="location.reload(); " class="btn btn-primary c-w">Torna al sito</a></div>';

    }
    
}

function sendForm(button, url, responseFunction = prettyFormResponse) {

    loadingSpinner();

    const VALUES = formToArray(button.form.elements);
    var form = JSON.stringify(VALUES);

    $.ajax({
        type: "POST",
        url: url,
        data: { 
            post: 'true',
            form: form
        }, 
        success: function (data) {

            if (responseFunction != null) {
                if (typeof responseFunction === 'function') {
                    responseFunction(data);
                } else {
                    loadingSpinner();
                }
            }

        },
        error: function (XMLHttpRequest) {
            ajaxRequestError(XMLHttpRequest);
            loadingSpinner();
        }
    });

}