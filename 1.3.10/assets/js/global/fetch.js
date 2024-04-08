function fetchResponse(response) {
    
    if (response.ok) {

        return response.json();

    } else {

        alertToast(802);
        console.error("Impossibile connettersi o trovare il file!");
        return false;

    }

}

function fetchValue(value) {
    
    if (value == false) {
                
        return false;
        
    } else {

        if (value.status == 200) {
            return value;
        } else if (value.status == 401) {
            alertToast(911);
            return false;
        } else if (value.status == undefined) {
            alertToast(803);
            return false;
        } else {
            alertToast(value.status);
            return false;
        }

    }

}

function fetchError(error) {
    
    alertToast(802);
    console.error("Il file non risponde in JSON!");

}
