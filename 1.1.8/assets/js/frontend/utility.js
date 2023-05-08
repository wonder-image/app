function loadingSpinner() {

    var spinner = document.getElementById('loading-spinner');

    if (spinner.classList.contains('d-none')) {

        spinner.classList.remove('d-none');
        spinner.classList.remove('no-interaction');

        disableScroll();
        
    } else {

        spinner.classList.add('d-none');
        spinner.classList.add('no-interaction');
        
        enableScroll();

    }
    
}