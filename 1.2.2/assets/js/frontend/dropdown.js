function toggleDropdown(container) {

    if (container.classList.contains('wi-show')) {
        closeDropdown(container);
    } else {

        container.classList.add('wi-show'); 

        if (container.querySelector('i.bi-chevron-down')) {
            container.querySelector('i.bi-chevron-down').classList.add('bi-chevron-up');
            container.querySelector('i.bi-chevron-down').classList.remove('bi-chevron-down');
        }

        if (container.querySelector('i.bi-plus')) {
            container.querySelector('i.bi-plus').classList.add('bi-dash');
            container.querySelector('i.bi-plus').classList.remove('bi-plus');
        }

        if (container.querySelector('i.bi-plus-lg')) {
            container.querySelector('i.bi-plus-lg').classList.add('bi-dash-lg');
            container.querySelector('i.bi-plus-lg').classList.remove('bi-plus-lg');
        }

    }
    
}

function openDropdown(container) {

    container.classList.add('wi-show'); 

    if (container.querySelector('i.bi-chevron-down')) {
        container.querySelector('i.bi-chevron-down').classList.add('bi-chevron-up');
        container.querySelector('i.bi-chevron-down').classList.remove('bi-chevron-down');
    }

    if (container.querySelector('i.bi-plus')) {
        container.querySelector('i.bi-plus').classList.add('bi-dash');
        container.querySelector('i.bi-plus').classList.remove('bi-plus');
    }

    if (container.querySelector('i.bi-plus-lg')) {
        container.querySelector('i.bi-plus-lg').classList.add('bi-dash-lg');
        container.querySelector('i.bi-plus-lg').classList.remove('bi-plus-lg');
    }
    
}

function closeDropdown(container) {

    container.classList.remove('wi-show'); 

    if (container.querySelector('i.bi-chevron-up')) {
        container.querySelector('i.bi-chevron-up').classList.add('bi-chevron-down');
        container.querySelector('i.bi-chevron-up').classList.remove('bi-chevron-up');
    }

    if (container.querySelector('i.bi-dash')) {
        container.querySelector('i.bi-dash').classList.add('bi-plus');
        container.querySelector('i.bi-dash').classList.remove('bi-dash');
    }

    if (container.querySelector('i.bi-dash-lg')) {
        container.querySelector('i.bi-dash-lg').classList.add('bi-plus-lg');
        container.querySelector('i.bi-dash-lg').classList.remove('bi-dash-lg');
    }
    
}

function setDropdown() {
    
    document.querySelectorAll(".wi-dropdown-btn .wi-switcher").forEach(element => {

        element.onclick = function() { toggleDropdown(element.parentElement); };
    
        element.parentElement.onmouseleave = function() { closeDropdown(element.parentElement); };
    
    });

    document.querySelectorAll(".wi-dropdown-box .wi-switcher").forEach(element => {

        if (element.parentElement.classList.contains('wi-show')) {
            openDropdown(element.parentElement);
        }

        element.onclick = function() { toggleDropdown(element.parentElement); };
    
    });

}