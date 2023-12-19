function menu(){ 

    var el = document.getElementById('sidebar');
    el.classList.toggle("show");

    var el = document.getElementById('menu');

    var iconMenu = el.querySelector('.open-menu');
    var iconClose = el.querySelector('.close-menu');

    if (iconMenu.classList.contains('d-none')) {
        iconMenu.classList.remove('d-none');
        iconClose.classList.add('d-none');
    } else {
        iconClose.classList.remove('d-none');
        iconMenu.classList.add('d-none');
    }
    
}

function loadingSpinner() {

    document.getElementById('loading-spinner').classList.toggle('d-none');
    
}