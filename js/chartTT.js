var tlTooltip = function(tooltip) {
    // Tooltip Element
    var tooltipEl = document.getElementById('chartjs-tooltip');

    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.id = 'chartjs-tooltip';
        tooltipEl.innerHTML = '<div class="text-left"></div>';
        this._chart.canvas.parentNode.appendChild(tooltipEl);
    }

    // Hide if no tooltip
    if (tooltip.opacity === 0) {
        tooltipEl.style.opacity = 0;
        return;
    }

    // Set caret Position
    tooltipEl.classList.remove('above', 'below', 'no-transform');
    if (tooltip.yAlign) {
        tooltipEl.classList.add(tooltip.yAlign);
    } else {
        tooltipEl.classList.add('no-transform');
    }

    function getBody(bodyItem) {
        return bodyItem.lines;
    }

    // Set Text
    if (tooltip.body) {
        if (tooltip.dataPoints[0].datasetIndex == 0) {
            var inid = 'killtt-'+tooltip.title[0];
            var type = 'Kills:';
        } else {
            var inid = 'losstt-'+tooltip.title[0];
            var type = 'Losses:';
        }
        var bodyLines = tooltip.body.map(getBody);

        var content = document.getElementById(inid).innerHTML;
        var innerHtml = '<div><span><b>'+type+'</b></span><br /><table class="table table-striped small lowpad">'+content+'</table>';

        var tableRoot = tooltipEl.querySelector('div');
        tableRoot.innerHTML = innerHtml;
    }

    var positionY = this._chart.canvas.offsetTop;
    var positionX = this._chart.canvas.offsetLeft;
    var bottom = this._chart.canvas.offsetTop + this._chart.canvas.height;

    // Display, position, and set styles for font
    tooltipEl.style.opacity = 1;
    tooltipEl.style.float = 'left';
    tooltipEl.style.position = 'absolute';
    tooltipEl.style.left = positionX + tooltip.caretX + 'px';
    tooltipEl.style.top = ((positionY + tooltip.caretY - bottom)/1.5 + bottom) + 'px';
    tooltipEl.style.fontSize = tooltip.bodyFontSize + 'px';
    tooltipEl.style.background = 'rgba(0, 0, 0, .7)';
    tooltipEl.style.color = 'white';
    tooltipEl.style.borderRadius = "3px";
    tooltipEl.style.transition = "all .1s ease";
    tooltipEl.style.pointerEvents = "none";
    tooltipEl.style.transform = "translate(0, -30%)";
    tooltipEl.style.padding = 8 + 'px ' + 8 + 'px';
};
