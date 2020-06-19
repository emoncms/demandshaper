<?php global $path; ?>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>
<h3>Forecast Viewer</h3>

<style>
.param_name {
    font-weight:bold;
    display:inline-block;
    width:120px;
}

.weight {
    width:50px;
    margin-bottom:0;
}

</style>

<table class="table">
<tr>
  <th>Forecast name</th>
  <th>Parameters</th>
  <th>Weight</th>
  <th></th>
</tr>
<tbody id="forecasts"></tbody>
</table>
<div class="input-prepend input-append"><span class="add-on">Add forecast</span><select id="forecast_list"></select></div>

<pre id="forecast_config_json"></pre>

<div id="placeholder_bound" style="width:100%; height:300px">
    <div id="placeholder" style="height:300px"></div>
</div>

<script>

// User defined forecast config
var forecast_config_str = localStorage.getItem('forecast_config');

if (!forecast_config_str) {
    forecast_config = [
        {"name":"octopusagile","gsp_id":"D","weight":1.0}
    ];
} else {
    forecast_config = JSON.parse(forecast_config_str);
}

var forecast_list = <?php echo json_encode($forecast_list); ?>;

// Draw dropdown selector for adding forecasts
var out = "<option value=0>Select forecast:</option>";
for (var z in forecast_list) {
    out += "<option value='"+z+"'>"+forecast_list[z].name+"</option>";
}
$("#forecast_list").html(out);

draw_forecast_config_table();

// -------------------------------------------------------

function draw_forecast_config_table() 
{
    // Draw forecast configuration UI
    var out = "";
    // Foreach forecast in forecast_list
    for (var forecast_id in forecast_config) {
        var forecast_key = forecast_config[forecast_id].name;

        out += "<tr>";
        out += "<td>"+forecast_list[forecast_key].name+"</td>";
        out += "<td>";
        
        // For each parameter in forecast
        for (var param_key in forecast_list[forecast_key].params) {
            var param_name = forecast_list[forecast_key].params[param_key].name;
            out += "<div>";
            out +="<div class='param_name'>"+param_name+"</div>";
            
            // If text entry box:
            if (forecast_list[forecast_key].params[param_key].type=="text") {
                var value = "";
                if (forecast_config[forecast_id][param_key]!=undefined) value = forecast_config[forecast_id][param_key];
                out += "<input class='param' type='text' data-param='"+param_key+"' data-fid="+forecast_id+" value='"+value+"' />";
                
            // If dropdown selector
            } else if (forecast_list[forecast_key].params[param_key].type=="select") {
                // each option in selector
                var options = "";
                for (var param_val in forecast_list[forecast_key].params[param_key].options) {
                    var selected = "";
                    if (forecast_config[forecast_id][param_key]==param_val) selected = "selected";
                    options += "<option value='"+param_val+"' "+selected+">"+forecast_list[forecast_key].params[param_key].options[param_val]+"</option>";
                }
                out += "<select class='param' data-param='"+param_key+"' data-fid="+forecast_id+" style='margin-bottom:0'>"+options+"</select>";
            }
            out += "</div>";
        }
        out += "</td>";
        
        var value = "";
        if (forecast_config[forecast_id].weight!=undefined) value = forecast_config[forecast_id].weight;

        out += "<td><input class='weight' type='text' data-fid="+forecast_id+" value='"+value+"' />";
        out += "<td><i class='icon-trash remove' data-fid="+forecast_id+" style='cursor:pointer'></i></td>";
        out += "</tr>";
    }
    $("#forecasts").html(out);

    save_config();
    get_forecast();
}
// -------------------------------------------------------

// ADD
$("#forecast_list").change(function(){
    var forecast_key = $("#forecast_list").val();
    var forecast_item = {"name":forecast_key,"weight":1.0};
    forecast_config.push(forecast_item);
    draw_forecast_config_table();
    setTimeout(function(){
        $("#forecast_list").val(0);
    },1000);
});

// REMOVE
$("#forecasts").on("click",".remove",function(){
    var fid = $(this).data("fid");
    forecast_config.splice(fid,1);
    draw_forecast_config_table();
});

// WEIGHT
$("#forecasts").on("change",".weight",function(){
    var forecast_id = $(this).data("fid");
    forecast_config[forecast_id].weight = $(this).val()*1;
    save_config();
    get_forecast(forecast);
});

// PARAMS
$("#forecasts").on("change",".param",function(){
    var forecast_id = $(this).data("fid");
    var param_key = $(this).data("param");
    var value = $(this).val();
    if (!isNaN(value)) value *= 1;
    forecast_config[forecast_id][param_key] = value;
    save_config();
    get_forecast();
});

function save_config() {
    $("#forecast_config_json").html(JSON.stringify(forecast_config));
    localStorage.setItem("forecast_config",JSON.stringify(forecast_config));
}

function get_forecast() {
    $.ajax({
        type: 'POST',
        data: "config="+JSON.stringify(forecast_config),
        url: path+"demandshaper/forecast", 
        dataType: 'json', 
        async: true, 
        success: function(result) {
            forecast = result;
            
            graph_data = [];
            var i=0;
            for (var time=forecast.start; time<forecast.end; time+=forecast.interval) {
                graph_data.push([time*1000,forecast.profile[i]]); i++;
            }
            
            draw_graph();
        }
    });
}

function draw_graph() {

    var options = {
        xaxis: { 
            mode: "time", 
            timezone: "browser", 
            font: {size:12, color:"#666"}, 
            reserveSpace:false
        },
        yaxis: { 
            font: {size:12, color:"#666"}, 
            reserveSpace:false
        },
        grid: {
            show:true, 
            color:"#aaa",
            borderWidth:0,
            hoverable: true
        },
        bars: { show: true, barWidth:1800*1000*0.8, lineWidth:0 }
    };

    var width = $("#placeholder_bound").width();
    if (width>0) {
        $("#placeholder").width(width);
        $.plot($('#placeholder'), [{data:graph_data,color:"#ea510e",fill:1.0}], options);
    }
}

</script>
