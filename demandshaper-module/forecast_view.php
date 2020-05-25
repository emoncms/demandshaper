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



<script>

// User defined forecast config
var forecast = [
    {"name":"octopusagile","gsp_id":"D","weight":1.0}
];

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
    for (var forecast_id in forecast) {
        var forecast_key = forecast[forecast_id].name;

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
                if (forecast[forecast_id][param_key]!=undefined) value = forecast[forecast_id][param_key];
                out += "<input class='param' type='text' data-param='"+param_key+"' data-fid="+forecast_id+" value='"+value+"' />";
                
            // If dropdown selector
            } else if (forecast_list[forecast_key].params[param_key].type=="select") {
                // each option in selector
                var options = "";
                for (var param_val in forecast_list[forecast_key].params[param_key].options) {
                    var selected = "";
                    if (forecast[forecast_id][param_key]==param_val) selected = "selected";
                    options += "<option value='"+param_val+"' "+selected+">"+forecast_list[forecast_key].params[param_key].options[param_val]+"</option>";
                }
                out += "<select class='param' data-param='"+param_key+"' data-fid="+forecast_id+" style='margin-bottom:0'>"+options+"</select>";
            }
            out += "</div>";
        }
        out += "</td>";
        
        var value = "";
        if (forecast[forecast_id].weight!=undefined) value = forecast[forecast_id].weight;

        out += "<td><input class='weight' type='text' data-fid="+forecast_id+" value='"+value+"' />";
        out += "<td><i class='icon-trash remove' data-fid="+forecast_id+" style='cursor:pointer'></i></td>";
        out += "</tr>";
    }
    $("#forecasts").html(out);

    $("#forecast_config_json").html(JSON.stringify(forecast));
}
// -------------------------------------------------------

// ADD
$("#forecast_list").change(function(){
    var forecast_key = $("#forecast_list").val();
    var forecast_item = {"name":forecast_key,"weight":1.0};
    forecast.push(forecast_item);
    draw_forecast_config_table();
    setTimeout(function(){
        $("#forecast_list").val(0);
    },1000);
});

// REMOVE
$("#forecasts").on("click",".remove",function(){
    var fid = $(this).data("fid");
    forecast.splice(fid,1);
    draw_forecast_config_table();
});

// WEIGHT
$("#forecasts").on("change",".weight",function(){
    var forecast_id = $(this).data("fid");
    forecast[forecast_id].weight = $(this).val()*1;
    $("#forecast_config_json").html(JSON.stringify(forecast));
});

// PARAMS
$("#forecasts").on("change",".param",function(){
    var forecast_id = $(this).data("fid");
    var param_key = $(this).data("param");
    var value = $(this).val();
    if (!isNaN(value)) value *= 1;
    forecast[forecast_id][param_key] = value;
    $("#forecast_config_json").html(JSON.stringify(forecast));
});



</script>
