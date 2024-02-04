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

async function postData(url, data) {

    loadingSpinner();

    await fetch(url, {
        method: "POST",
        body: data
    })
    .then((response) => {

        loadingSpinner();

        if (response.ok) {

            return response.json();

        } else {

            alertToast(802);
            console.log("Impossibile connettersi o trovare il file!");
            return false;

        }

    })
    .then((value) => {

        if (value == false) {
            
            return false;
            
        } else {

            if (value.status == 200) {
                return value;
            } else if (value.status == 401) {
                alertToast(911);
                return false;
            } else {
                alertToast(value.status);
                return false;
            }

        }

    }).catch((error) => {

        alertToast(802);
        console.log("Il file non risponde in JSON!");

    });

}
