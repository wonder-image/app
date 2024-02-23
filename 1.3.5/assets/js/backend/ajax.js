function ajaxRequest(link, onSuccess = reloadPage, params = null) {

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
                    
                    if (params == null) {
                        onSuccess();
                    } else {
                        onSuccess(params);
                    }
            
                } else {
            
                    var x = JSON.parse(onSuccess.replace('&quot;', '"'));
            
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

async function postData(url, data) {

    loadingSpinner();

    if (data === undefined) {

        console.error("Non è stato passato alcun dato alla funzione postData()");
        
    } else {

        if (data instanceof FormData === false) {
            var data = createPostData(data)
        }
        
        await fetch(url, {
            method: "POST",
            body: data
        })
        .then((response) => { loadingSpinner(); return fetchResponse(response); })
        .then((value) => { return fetchValue(value); })
        .catch((error) => { fetchValue(error); });

    }

}

function createPostData(array = {}) {

    var data = new FormData();

    Object.keys(array).forEach(key => {
        data.append(key, array[key]);
    });

    return data;

}