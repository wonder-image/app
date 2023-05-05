if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
    document.getElementById('aos-css').href = '';
    document.getElementById('aos-js').src = '';
}else{
    AOS.init();
}