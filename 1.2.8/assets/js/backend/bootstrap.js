function bootstrapToast() {

    var toastElList = [].slice.call(document.querySelectorAll('.toast'))
    var toastList = toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl)
    });
    toastList.forEach(toast => toast.show());

}

function bootstrapTooltip() {

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

}

function bootstrapTheme(theme) {

    document.querySelector("html").setAttribute("data-bs-theme", theme);

    localStorage.setItem('theme', theme);
    
    if (theme == 'light') {

        if (document.querySelector('#bs-theme i.light-theme')) { document.querySelector('#bs-theme i.light-theme').classList.remove('d-none'); }
        if (document.querySelector('#bs-theme i.dark-theme')) { document.querySelector('#bs-theme i.dark-theme').classList.add('d-none'); }

        document.querySelector('#be-logo-white').classList.remove('d-none');
        document.querySelector('#be-logo-black').classList.add('d-none');

        element = document.querySelector('[data-bs-theme-value="light"]');
        el = document.querySelector('[data-bs-theme-value="dark"]');

    } else if (theme == 'dark') {

        if (document.querySelector('#bs-theme i.light-theme')) { document.querySelector('#bs-theme i.light-theme').classList.add('d-none'); }
        if (document.querySelector('#bs-theme i.dark-theme')) { document.querySelector('#bs-theme i.dark-theme').classList.remove('d-none'); }

        document.querySelector('#be-logo-white').classList.remove('d-none');
        document.querySelector('#be-logo-black').classList.add('d-none');

        element = document.querySelector('[data-bs-theme-value="dark"]');
        el = document.querySelector('[data-bs-theme-value="light"]');

    }

    el.classList.remove('active');
    element.classList.add('active');

}

function setUpBootstrap() {
    
    bootstrapTooltip();

}