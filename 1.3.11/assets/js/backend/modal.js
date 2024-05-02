function modal(text, link, title = 'ATTENZIONE', sendText = 'Elimina', sendColor = 'danger', closeText = 'Chiudi', closeColor = 'dark', onSuccess = 'reloadPage', params = null) {
    
    document.querySelector('#modal .modal-title').innerHTML = title;
    document.querySelector('#modal .modal-body').innerHTML = text;

    var sendBtn = document.querySelector('#modal button.send');
    sendBtn.innerHTML = sendText;
    sendBtn.classList.add('btn-'+sendColor);

    if (onSuccess == 'reloadPage') {
        
        sendBtn.setAttribute("onclick", "ajaxRequest('"+link+"')");

    } else {
        
        if (typeof onSuccess === 'function') {
            sendBtn.setAttribute("onclick", "ajaxRequest('"+link+"', "+onSuccess+", '"+params+"')");
        } else {
            sendBtn.setAttribute("onclick", "ajaxRequest('"+link+"', '"+onSuccess+"', '"+params+"')");
        }

    }

    var closeBtn = document.querySelector('#modal button.close');
    closeBtn.innerHTML = closeText;
    closeBtn.classList.add('btn-'+closeColor);

    const modal = new bootstrap.Modal('#modal')
    modal.show();

}