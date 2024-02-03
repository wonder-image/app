function formUpload(form, url, responseFunction = null) {

    loadingSpinner();
    
    var formData = new FormData(form);
    formData.append('post', 'true');
    
    $.ajax({
        type: "POST",
        url: url,
        data: formData,
        contentType: false,
        cache: false,
        processData:false, 
        success: function (data) {

            if (responseFunction == null) {
                loadingSpinner();
            } else if (typeof responseFunction === 'function') {
                responseFunction(data);
            }

        },
        error: function (XMLHttpRequest) {
            ajaxRequestError(XMLHttpRequest);
            loadingSpinner();
        }
    });

}