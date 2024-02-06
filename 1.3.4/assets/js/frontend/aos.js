if(isMobile() && (typeof MOBILE_REMOVE_AOS == 'undefined' || MOBILE_REMOVE_AOS == true)) {
    document.getElementById('aos-css').href = '';
    document.getElementById('aos-js').src = '';
} else {
    AOS.init();
}