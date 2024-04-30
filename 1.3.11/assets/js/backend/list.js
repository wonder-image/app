function reloadDataTable(selector) {

    $(selector).DataTable().ajax.reload(null, false) 
    
}

function setListRedirect(url) {
    
    window.history.replaceState(null, null, url);

    if (document.querySelector('#wi-add-button')) {

        var addButton = new URL(document.querySelector('#wi-add-button').href);
        addButton.searchParams.set('redirect', btoa(url));
        document.querySelector('#wi-add-button').href = addButton;

    }

}
