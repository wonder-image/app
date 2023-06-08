function alertContainer() {

    if (document.querySelector('.alert-container')) {

        var container = document.querySelector('.alert-container');

    }else{

        var container = document.createElement('div');
        container.classList.add('alert-container');
        container.classList.add('p-f');
        container.classList.add('top');
        container.classList.add('start');
        container.classList.add('w-100');
        container.classList.add('h-100');
        container.classList.add('no-interaction');
        container.style.zIndex = "1000";

        document.body.insertBefore(container, document.body.firstChild);

    }

    return container;

}

function alertToast(alert) {

    var container = alertContainer();

    if (alert == 801) {

        container.innerHTML += NO_INTERNET_ALERT;

    } else {

        $.ajax({
            type: "POST",
            url: pathApp+'/api/alert.php',
            data: { 
                post: 'true',
                frontend: 'true',
                alert: alert
            }, 
            success: function (data) {

                container.innerHTML += data;

            }
        });

    }

    setTimeout(() => {
        document.querySelectorAll('.wi-alert.wi-show').forEach(element => {
            element.classList.remove('wi-show');
        });
    }, 4000);

}