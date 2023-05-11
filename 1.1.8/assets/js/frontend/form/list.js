function searchInput(event) {
    
    var inputId = event.target.id;
    var inputValue = event.target.value.toLowerCase();

    document.querySelectorAll('#list_'+inputId+' input').forEach(x => {
        
        var keyword = x.dataset.wiKeyword.toLowerCase();

        if (keyword.includes(inputValue)) {
            x.parentElement.style.display = 'block';
        }else{
            x.parentElement.style.display = 'none';
        }
        
    });

}

function checkInput(event) {
    
    var container = event.target;

    if (container.localName == 'div') {
        var checkbox = container.querySelector('input');
    } else {
        var checkbox = container;
    }

    var inputId = checkbox.dataset.wiInput;

    checkbox.checked = true;

    var input = document.querySelector('#'+inputId);
    input.value = checkbox.dataset.wiName;

}

function controlList(event) {

    var element = event.target;
    var container = event.target.parentElement;
    var spanAlert = container.querySelector('.alert-error');
    
    var l = element.dataset.wiListArray.split('|');
    var v = element.value;

    if (v != '') {
        if (l.includes(v)) {

            spanAlert.innerHTML = "";
            container.classList.remove('input-error');
            element.setCustomValidity('');
            container.querySelectorAll('input[type=radio]').forEach(e => {
                if (e.dataset.name == v) {
                    e.checked = true;
                }else{
                    e.checked = false;
                }
            });
    
        } else {
    
            spanAlert.innerHTML = "<i class='bi bi-exclamation-triangle'></i> Campo non corretto!";
            element.setCustomValidity('Non valido');
            container.classList.add('input-error');
    
            container.querySelectorAll('input[type=radio]:checked').forEach(e => {
                e.checked = false;
            });
    
        }
    }

}

function showList(event) { event.target.parentElement.classList.add('wi-list-active'); }

function hideList(event) { event.target.parentElement.classList.remove('wi-list-active'); }

function searchText(event) { searchResults(event, "text"); }

function searchRadio(event) { searchResults(event, "radio"); }

function searchResults(event, result) {

    var input = event.target;

    var container = input.parentElement;

    var list = container.querySelector('.wi-input-list');
    var listValue = list.querySelector('.wi-input-list-body');
    var listFooter = list.querySelector('.wi-input-list-footer');

    var inputId = input.id;
    var inputName = input.dataset.wiName;
    var inputValue = input.value.toLowerCase();

    var inputUrl = input.dataset.wiSearchUrl;

    if (result == 'radio') {
        var radioName = "name='"+inputName+"'";
    } else {
        var radioName = "";
    }

    $.ajax({
        type: "POST",
        url: inputUrl,
        data: { 
            post: 'true',
            search: inputValue
        }, 
        success: function (data) {

            const response = JSON.parse(data);
            const nResponse = response.length;
            
            var listHTML = "";

            for (let index = 0; index < nResponse; index++) {

                const value = response[index]['value'];
                const label = response[index]['label'];
                const inputValue = response[index]['input-value'];

                listHTML += "<div class='wi-input-list-value' data-wi-list-value='true'><input data-wi-input='"+inputId+"' type='radio' "+radioName+" data-wi-name='"+inputValue+"' value='"+value+"'>"+label+"</div>";

            }

            if (inputValue == '') {
                listFooter.innerHTML = "Cosa stai cercando?";
            } else if (nResponse == 1) {
                listFooter.innerHTML = nResponse+" risultato";
            } else if (nResponse != 1) {
                listFooter.innerHTML = nResponse+" risultati";
            }

            listValue.innerHTML = listHTML;

            document.querySelectorAll("[data-wi-list-value='true']").forEach(element => {
                element.addEventListener("click", checkInput);
                element.addEventListener("mousedown", checkInput);
            });

        },
        error: function (XMLHttpRequest) {
            ajaxRequestError(XMLHttpRequest);
            loadingSpinner();
        }
    });

}