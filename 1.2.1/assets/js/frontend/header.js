function menuMobile(hamburger = "#hamburger", navMobile = "#nav-mobile") { 
            
    if (hamburger != null) { document.querySelector(hamburger).classList.toggle("click"); }
    if (navMobile != null) { document.querySelector(navMobile).classList.toggle("show"); }

    if (document.querySelector(hamburger).classList.contains("click")) {
        disableScroll();
    } else {
        enableScroll();
    }

}