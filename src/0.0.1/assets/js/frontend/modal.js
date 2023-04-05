function modal(selector) {

    setUpModal(selector);

    var modal = document.querySelector(selector);

    modal.classList.toggle('wi-show');
    modal.classList.toggle('no-interaction');

    if (modal.classList.contains('wi-show')) {
        disableScroll();
    } else {
        enableScroll();
    }

}

function setUpModal(selector) {
    
    document.querySelectorAll(selector + ' .wi-close-modal').forEach(element => {
        if (element.onclick == null) {
            element.onclick = function() { modal(selector) };
        }
    });

}