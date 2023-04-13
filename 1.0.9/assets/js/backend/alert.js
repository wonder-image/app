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

function alertToast(alert) {

    var container = toastContainer();

    if (alert == 801) {

        container.innerHTML = NO_INTERNET_ALERT + container.innerHTML;
        const toast = new bootstrap.Toast(container.lastElementChild);
        toast.show();

    } else {

        $.ajax({
            type: "POST",
            url: pathApp+'/api/alert.php',
            data: { 
                post: 'true',
                backend: 'true',
                alert: alert
            }, 
            success: function (data) {

                container.innerHTML = data + container.innerHTML;
                const toast = new bootstrap.Toast(container.lastElementChild);
                toast.show();
                
            }
        });

    }

}