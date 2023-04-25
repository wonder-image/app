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

function showList(event) {
    event.target.parentElement.classList.add('wi-list-active');
}

function hideList(event) {
    event.target.parentElement.classList.remove('wi-list-active');
}