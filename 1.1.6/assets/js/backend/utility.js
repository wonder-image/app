function copyText(text, icon = null) {

    if (icon != null) {
        document.querySelectorAll('.bi-clipboard-check').forEach(element => {
            element.classList.remove('bi-clipboard-check')
            element.classList.add('bi-clipboard');
        });
    }

    navigator.clipboard.writeText(text).then(() => {
        if (icon != null) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-clipboard-check');
        }
    },() => {
        if (icon != null) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-clipboard-x');
        }
    });

}

function menu(){ 

    var el = document.getElementById('sidebar');
    el.classList.toggle("show");

    var el = document.getElementById('menu');
    el.classList.toggle("click");
    
}

function loadingSpinner() {

    document.getElementById('loading-spinner').classList.toggle('d-none');
    
}

function code(codeLength = 10) {

    var result           = '';
    var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var charactersLength = characters.length;

    for ( var i = 0; i < codeLength; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }

    return result;

}