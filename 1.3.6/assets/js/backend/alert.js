function toastContainer() {

    if (document.querySelector('.toast-container')) {

        var container = document.querySelector('.toast-container');

    }else{

        var container = document.createElement('div');
        container.classList.add('toast-container');
        container.classList.add('position-fixed');
        container.classList.add('top-0');
        container.classList.add('end-0');
        container.classList.add('p-3');

        document.body.insertBefore(container, document.body.firstChild);

    }

    return container;

}

function alertToast(alert, type = null, title = null, text = null) {

    var container = toastContainer();

    if (alert == 801) {

        container.innerHTML = NO_INTERNET_ALERT + container.innerHTML;
        const toast = new bootstrap.Toast(container.firstElementChild);
        toast.show();

    } else if (alert != undefined) {

        if (alert == 'custom') {

            var data = {
                post: 'true',
                backend: 'true',
                alert: alert,
                alertType: type,
                alertTitle: title,
                alertText: text
            }

        } else {

            var data = {
                post: 'true',
                backend: 'true',
                alert: alert
            };

        }

        $.ajax({
            type: "POST",
            url: pathApp+'/api/alert.php',
            data: data, 
            success: function (data) {

                container.innerHTML = data + container.innerHTML;
                const toast = new bootstrap.Toast(container.firstElementChild);
                toast.show();
                
            }
        });

    }

}