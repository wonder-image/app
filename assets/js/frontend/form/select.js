var x, i, j, l, ll, selElmnt, a, b, c;

// Look for any elements with the class "custom-select":
x = document.querySelectorAll("[data-wi-select='true']");
l = x.length;

for (i = 0; i < l; i++) {

    selElmnt = x[i].getElementsByTagName("select")[0];
    ll = selElmnt.length;

    // For each element, create a new DIV that will act as the selected item:
    a = document.createElement("DIV");
    a.setAttribute("class", "select-selected wi-input");
    a.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
    x[i].appendChild(a);

    // For each element, create a new DIV that will contain the option list:
    b = document.createElement("DIV");
    b.setAttribute("class", "select-items wi-input-list no-scrollbar");

    for (j = 0; j < ll; j++) {

        // For each option in the original select element, create a new DIV that will act as an option item:
        c = document.createElement("DIV");
        c.id = selElmnt.options[j].value;
        c.setAttribute("class", "wi-input-list-value");
        c.innerHTML = selElmnt.options[j].innerHTML;

        c.addEventListener("click", function(e) {

            // When an item is clicked, update the original select box, and the selected item:
            var y, i, k, s, h, sl, yl;
            s = this.parentNode.parentNode.getElementsByTagName("select")[0];
            sl = s.length;
            selElmnt.value = this.id;
            h = this.parentNode.previousSibling;

            for (i = 0; i < sl; i++) {
                if (s.options[i].innerHTML == this.innerHTML) {
                    s.selectedIndex = i;
                    h.innerHTML = this.innerHTML;
                    y = this.parentNode.getElementsByClassName("same-as-selected");
                    yl = y.length;
                    for (k = 0; k < yl; k++) {
                        y[k].classList.remove("same-as-selected");
                    }
                    this.setAttribute("class", "wi-input-list-value same-as-selected");
                    break;
                }
            }

            h.click();

        });

        b.appendChild(c);

    }

    x[i].appendChild(b);

    a.addEventListener("click", function(e) {

        // When the select box is clicked, close any other select boxes, and open/close the current select box
        e.stopPropagation();
        closeAllSelect(this);
        this.classList.toggle("select-arrow-active");
        this.parentNode.classList.toggle("wi-list-active");
        check();

    });

}

function closeAllSelect(elmnt) {

    // A function that will close all select boxes in the document, except the current select box:
    var x, y, i, xl, yl, arrNo = [];
    x = document.getElementsByClassName("select-items");
    y = document.getElementsByClassName("select-selected");
    var container = document.querySelector(".wi-input-container.wi-list-active");
    xl = x.length;
    yl = y.length;

    for (i = 0; i < yl; i++) {
        if (elmnt == y[i]) {
            arrNo.push(i)
        }else{
            y[i].classList.remove("select-arrow-active");
        }
    }

    if (container != null) {
        container.classList.remove("wi-list-active");
    }

}

// If the user clicks anywhere outside the select box, then close all select boxes:
document.addEventListener("click", closeAllSelect);