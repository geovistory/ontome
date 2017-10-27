$(document).ready(function() {
    $.ajax({
        url: $('#legend-container').data('url')
    }).done(function(data) {
        data = JSON.parse(data);
        var y = 10;
        $.each(data, function(key, val){
            $("#legend-container").append('<g class="node" transform="translate(50,'+y+')"><circle r="4.5" stroke="'+val['css_color']+'" fill="fff" style="fill: rgb(255, 255, 255);"/><text x="10" dy=".35em" text-anchor="start" style="fill-opacity: 1;"><tspan x="10" dy="0.35em">'+val['label']+'</tspan></text></g>');

            y = y+15;
            //console.log((val['css_color']));
        });
        $("#legend-container").html($("#legend-container").html()); //refresh svg container
    });
});