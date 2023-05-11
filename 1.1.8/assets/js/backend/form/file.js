function moveFile(selectorContainer, selectorData, action) {

    var link = pathApp+'/api/backend/file/move.php';

    var el = document.querySelector(selectorContainer+' '+selectorData);

    var table = el.dataset.wiDbTable;
    var column = el.dataset.wiDbColumn;
    var rowId = el.dataset.wiDbRow;
    var fileId = el.dataset.wiFileId;
    var oldPosition = Number(el.dataset.wiOrder);
    var nFile = el.dataset.wiNFile;

    $.ajax({
        type: "POST",
        url: link,
        data: { 
            post: 'true',
            table: table,
            column: column,
            row_id: rowId,
            file_id: fileId,
            action: action
        },
        success: function (data) {
            
            if (action == 'up') {
                var newPosition = oldPosition - 1;
            } else if (action == 'down') {
                var newPosition = oldPosition + 1;
            }

            var file1 = document.querySelector(selectorContainer+' .wi-card-file.order-'+oldPosition);
            var file2 = document.querySelector(selectorContainer+' .wi-card-file.order-'+newPosition);

            file1.classList.remove('order-'+oldPosition);
            file1.classList.add('order-'+newPosition);
            file1.dataset.wiOrder = newPosition;
            file1.dataset.wiFileId = file2.dataset.wiFileId;

            file2.classList.remove('order-'+newPosition);
            file2.classList.add('order-'+oldPosition);
            file2.dataset.wiOrder = oldPosition;
            file2.dataset.wiFileId = fileId;

            // Sistema first e last
            firstLastFile(selectorContainer, nFile);
            
        },
        error: function (XMLHttpRequest) {
            ajaxRequestError(XMLHttpRequest);
        }
    }); 

}

function deleteFile(selectorContainer, selectorData) {

    var el = document.querySelector(selectorContainer+' '+selectorData);

    var folder = el.dataset.wiFolder;
    var table = el.dataset.wiDbTable;
    var column = el.dataset.wiDbColumn;
    var rowId = el.dataset.wiDbRow;
    var fileId = el.dataset.wiFileId;

    var oldPosition = Number(el.dataset.wiOrder);
    var nFile = el.dataset.wiNFile;

    var fileName = el.dataset.wiFileName;

    var text = "Sei sicuro di eliminare il file <b>"+fileName+"</b>?";
    var link = pathApp+'/api/backend/file/delete.php?folder='+folder+'&table='+table+'&column='+column+'&row_id='+rowId+'&file_id='+fileId;

    var onSuccess = '{ "function" : "removeFile", "parameters": { "container" : "'+selectorContainer+'", "data" : "'+selectorData+'", "nFile" : "'+nFile+'", "position" : "'+oldPosition+'"} }';

    modal(text, link, 'ATTENZIONE', 'Elimina', 'danger', 'Chiudi', 'dark', onSuccess);

}

function removeFile(parameters) {

    var selectorContainer = parameters.container;
    var selectorData = parameters.data;
    var deletedFilePosition = Number(parameters.position);
    var maxFile = Number(parameters.maxFile);
    var old_nFile = Number(parameters.nFile);
    const real_nFile = old_nFile - 1;

    document.querySelector(selectorContainer+' '+selectorData).remove();

    document.querySelectorAll(selectorContainer+' .wi-card-file').forEach(element => {

        var filePosition = Number(element.dataset.wiOrder);
        element.dataset.wiNFile = real_nFile;

        if (filePosition > deletedFilePosition) {

            var newPosition = filePosition - 1;
            var newId = newPosition - 1;

            element.dataset.wiOrder = newPosition;
            element.classList.remove('order-'+filePosition);
            element.classList.add('order-'+newPosition);

            element.dataset.wiFileId = newId;

        }

    });


    var maxFile = document.querySelector(selectorContainer+' input').dataset.wiMaxFile;

    if (maxFile > real_nFile) {
        document.querySelector(selectorContainer+' input').disabled = false;
    }

    firstLastFile(selectorContainer, real_nFile);

    $('#modal').modal('hide');

}

function firstLastFile(selectorContainer, nFile) {

    if (document.querySelectorAll(selectorContainer+' .wi-card-file').length > 0) {

        if (document.querySelector(selectorContainer+' .wi-card-file.wi-first-file')) {
            document.querySelector(selectorContainer+' .wi-card-file.wi-first-file').classList.remove('wi-first-file');
        }
    
        if (document.querySelector(selectorContainer+' .wi-card-file.wi-last-file')) {
            document.querySelector(selectorContainer+' .wi-card-file.wi-last-file').classList.remove('wi-last-file');
        }
    
        document.querySelector(selectorContainer+' .wi-card-file.order-1').classList.add('wi-first-file');
        document.querySelector(selectorContainer+' .wi-card-file.order-'+nFile).classList.add('wi-last-file');
        
    }

}