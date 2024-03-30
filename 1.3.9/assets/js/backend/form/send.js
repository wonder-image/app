async function formUpload(form, url, responseFunction = null) {
    
    var formData = new FormData(form);
    formData.append('post', 'true');

    await postData(url, formData).then((data) => {
    
        if (data != false) {
            if (typeof responseFunction === 'function') { responseFunction(data); }
        }

    });

}