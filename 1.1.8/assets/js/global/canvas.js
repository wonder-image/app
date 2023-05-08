function canvasFullPage(elementId, draw = false) {

    var element = document.createElement("CANVAS");
    element.id = elementId;
    element.style.position = "fixed";
    element.style.top = 0;
    element.style.left = 0;
    element.style.zIndex = 999999;
    element.style.background = '#ffffff';
    element.style.touchAction = 'none';
    element.style.msTouchAction = 'none';

    fullPage(element);

    window.addEventListener('resize', () => {
        fullPage(element)
    });

    if (draw) { canvasDraw(element); }

}

function canvasReset(element) {
    
    var ctx = element.getContext("2d");
    ctx.clearRect(0, 0, element.width, element.height);
    ctx.restore();

}

function canvasDraw(element) {

    var ctx = element.getContext("2d");
    
    let offset = position(element);
    let painting = false;

    function drawStart() {
        offset = position(element);
        painting = true;
        draw(e);
    }

    function drawEnd() {
        painting = false;
        ctx.beginPath();
    }

    function draw(e) {

        if (!painting) return;
        ctx.lineWidth = 1;
        ctx.lineCap = "round";
        ctx.strokeStyle = "black";

        if (e.type == 'touchmove'){
            var x = e.touches[0].clientX;
            var y = e.touches[0].clientY;
        } else if (e.type == 'mousemove'){
            var x = e.clientX;
            var y = e.clientY;
        }

        var offsetX = offset.left;
        var offsetY = offset.top;

        var x = x - offsetX;
        var y = y - offsetY;

        ctx.lineTo(x, y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x, y);

    }

    element.addEventListener("mousedown", drawStart);
    element.addEventListener("touchstart", drawStart);
    element.addEventListener("mouseup", drawEnd);
    element.addEventListener("touchend", drawEnd);
    element.addEventListener("mousemove", draw);
    element.addEventListener("touchmove", draw);

}