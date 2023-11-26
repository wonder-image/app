function menu(){ 

    var el = document.getElementById('sidebar');
    el.classList.toggle("show");

    var el = document.getElementById('menu');
    el.classList.toggle("click");
    
}

function loadingSpinner() {

    document.getElementById('loading-spinner').classList.toggle('d-none');
    
}