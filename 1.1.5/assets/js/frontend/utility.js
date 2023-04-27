function copyText(text, icon = null) {

    if (icon != null) {
        document.querySelectorAll('.bi-clipboard-check').forEach(element => {
            element.classList.remove('bi-clipboard-check')
            element.classList.add('bi-clipboard');
        });
    }

    navigator.clipboard.writeText(text).then(() => {
        if (icon != null) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-clipboard-check');
        }
    },() => {
        if (icon != null) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-clipboard-x');
        }
    });

}

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

function ajaxRequestError(request) {
    
    if (request.readyState == 4) {
        alertToast(802);
    } else if (request.readyState == 0) {
        alertToast(801);
    } else {
        alertToast(800);
    }

}