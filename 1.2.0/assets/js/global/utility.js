function position(element, showItem = false) {

    var rect = element.getBoundingClientRect();

    if (showItem) {

        var width = rect.right - rect.left;
        var height = rect.bottom - rect.top;

        var element = document.createElement("DIV");
        element.style.position = "fixed";
        element.style.top = rect.top+'px';
        element.style.left = rect.left+'px';
        element.style.width = width+'px';
        element.style.height = height+'px';
        element.style.zIndex = 999999;
        element.style.background = 'rgba(255, 0, 0, .2)';
        document.body.appendChild(element);

    }

    return rect;

}

function fullPage(element) {
    element.width = window.innerWidth;
    element.height = window.innerHeight;
}

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

function ajaxRequestError(request) {
    
    if (request.readyState == 4) {
        alertToast(802);
    } else if (request.readyState == 0) {
        alertToast(801);
    } else {
        alertToast(800);
    }

}
