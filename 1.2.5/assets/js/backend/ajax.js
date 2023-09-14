function ajaxRequest(link, onSuccess = reloadPage) {

    loadingSpinner();

    $.ajax({
        type: "POST",
        url: link,
        data: { 
            post: 'true'
        }, 
        success: function(data) {

            if (onSuccess != null) {
                if (typeof onSuccess === 'function') {
                    
                    onSuccess();
            
                } else {
            
                    var x = JSON.parse(onSuccess);
            
                    var f = x.function;
                    var p = x.parameters;
            
                    window[f](p);
            
                }
            }

            loadingSpinner();
            
        },
        error: function (XMLHttpRequest) {
            ajaxRequestError(XMLHttpRequest);
            loadingSpinner();
        }
    }); 

}

function reloadPage() {

    location.reload();
    
}