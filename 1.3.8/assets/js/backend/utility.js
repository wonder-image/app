function menu() { 

    var el = document.getElementById('sidebar');
    el.classList.toggle("show");

    var el = document.getElementById('menu');

    var iconMenu = el.querySelector('.open-menu');
    var iconClose = el.querySelector('.close-menu');

    if (iconMenu.classList.contains('d-none')) {

        // Chiudo il menu
        iconMenu.classList.remove('d-none');
        iconClose.classList.add('d-none');

        $('.offcanvas').offcanvas('hide');
        
    } else {

        // Apro il menu
        iconClose.classList.remove('d-none');
        iconMenu.classList.add('d-none');

        if (document.querySelector('#sidebar li.active [data-bs-target]')) {
            var offcanvasId = document.querySelector('#sidebar li.active [data-bs-target]').dataset.bsTarget;
            $(offcanvasId).offcanvas('show');
        }

    }
    
}

function loadingSpinner() {

    document.getElementById('loading-spinner').classList.toggle('d-none');
    
}