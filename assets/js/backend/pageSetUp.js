function createCard() {

    document.querySelectorAll("wi-card").forEach(element => {

        var card = document.createElement("div");
        card.classList.add('card');
        var cardBody = document.createElement("div");
        cardBody.classList.add('card-body');
        cardBody.classList.add('row');
        cardBody.classList.add('g-3');
        cardBody.innerHTML = element.innerHTML;
        card.appendChild(cardBody);

        element.innerHTML = '';
        element.appendChild(card);

    });
    
}

function checkInput() {

    document.querySelectorAll("[data-wi-counter='true']").forEach(element => {
        element.addEventListener("keyup", lengthCount);
        element.addEventListener("change", lengthCount);
        element.addEventListener("focusin", lengthCount);
        element.addEventListener("focusout", lengthCount);
    });

    document.querySelectorAll("[data-wi-check='true']").forEach(element => {
        element.addEventListener("keyup", check);
        element.addEventListener("change", check);
        element.addEventListener("focusin", check);
        element.addEventListener("focusout", check);
    });

    check();

}

function pageRemove(element) {

    element.remove();
    
    checkInput();

}

async function setUpPage() {
    await createCard();
    checkInput();
    bootstrapTooltip();
};