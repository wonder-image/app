function copyRow(container, element) {

    var copy = element.cloneNode(true);
    var position = container.childElementCount;

    copy.id = '';
    copy.classList.remove('visually-hidden');
    copy.classList.add('wi-copy-row');
    copy.classList.add('order-'+position);

    copy.querySelector('input[name="position[]"]').value = position;

    copy.querySelectorAll('[data-wi-attribute]').forEach(element => {

        var attribute = element.dataset.wiAttribute;

        if (attribute != '') {

            if (attribute.includes('required')) { element.required = true; }
            if (attribute.includes('disabled')) { element.disabled = true; }
            if (attribute.includes('readonly')) { element.readonly = true; }

        }

    });

    container.appendChild(copy);
    
    checkInput();
    rowSetArrow(container)

}

function rowOrder(el1, action) {

    var container = el1.parentElement;
    
    for (let a = 1; a < 100; a++) { 
        
        var classEl1 = 'order-'+a;

        if(el1.classList.contains(classEl1)) {

            if (action == 'up') { var b = a - 1; } 
            if (action == 'down') { var b = a + 1; }

            var classEl2 = 'order-'+b;

            var el2 = container.querySelector('.'+classEl2);

            rowChangePos(el1, a, b);
            rowChangePos(el2, b, a);

            rowSetArrow(container);

            break;

        }

    }

}

function rowChangePos(element, oldPosition, newPosition) {
    
    element.classList.remove('order-'+oldPosition);
    element.classList.add('order-'+newPosition);

    if (element.querySelector('input[name="position[]"]')) {
        element.querySelector('input[name="position[]"]').value = newPosition;
    }
    
}

function rowRemoveModal(element) {

    var container = element.parentElement;

    var text = 'Sei sicuro di voler eliminare questa linea?<br>Se <b>NON</b> salvi questa operazione non verrà effettuata!'
    modal(text, 'link');

    for (let a = 1; a < 100; a++) { 

        var elementClass = 'order-'+a;
        if(element.classList.contains(elementClass)) { 
            var orderN = a;
            break; 
        }

    }

    var selector = '#'+container.id+' .'+elementClass;

    var sendBtn = document.querySelector('#modal button.send');
    sendBtn.setAttribute("onclick", 'rowRemove("'+selector+'", "'+orderN+'")');

}

function rowRemove(selector, nRow) {

    $('#modal').modal('hide');

    var element = document.querySelector(selector);
    var container = element.parentElement;
    var containerId = container.id;
    pageRemove(element);

    var rowStart = (nRow * 1) + 1;

    for (let a = rowStart; a < 100; a++) {

        var selector = '#'+containerId+' .order-'+a;

        if (document.querySelector(selector)) {
            var b = a - 1;
            rowChangePos(document.querySelector(selector), a, b);
        } else {
            break;
        }
        
    }

    rowSetArrow(container)

}

function rowSetArrow(container) {

    var nRow = document.querySelectorAll('#'+container.id+' .wi-copy-row').length;

    if (nRow > 1) {

        for (let i = 1; i <= nRow; i++) {

            var arrowUp = document.querySelector('#'+container.id+' .wi-copy-row.order-'+i+' .wi-arrow-up');
            var arrowDown = document.querySelector('#'+container.id+' .wi-copy-row.order-'+i+' .wi-arrow-down');

            if (arrowUp.classList.contains('visually-hidden')) {
                arrowUp.classList.remove('visually-hidden');
            }

            if (arrowDown.classList.contains('visually-hidden')) {
                arrowDown.classList.remove('visually-hidden');
            }

            if (i == 1) {
                arrowUp.classList.add('visually-hidden');
            }

            if (i == nRow) {
                arrowDown.classList.add('visually-hidden');
            }
            
        }

    } else if (nRow == 1) {

        document.querySelector('#'+container.id+' .wi-copy-row.order-1 .wi-arrow-up').classList.add('visually-hidden');
        document.querySelector('#'+container.id+' .wi-copy-row.order-1 .wi-arrow-down').classList.add('visually-hidden');

    }
    
}