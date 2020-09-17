/*
  FORECAST BUILDER

  Note: Requires html of the form:

  <table class="table">
    <tr><th>Forecast name</th><th>Parameters</th><th>Weight</th><th></th></tr>
    <tbody id="forecasts"></tbody>
  </table>
  <div class="input-prepend input-append"><span class="add-on">Add forecast</span><select id="forecast_list"></select></div>
*/

var forecast_builder = {
    table_element:"#forecasts",
    list_element:"#forecast_list",
    config: [],

    init: function(table_element,list_element,config,on_change_callback) 
    {
        forecast_builder.table_element = table_element;
        forecast_builder.list_element = list_element;
        forecast_builder.config = config;
        
        // Draw dropdown selector for adding forecasts
        var out = "<option value=0>Select forecast:</option>";
        for (var z in forecast_list) {
            out += "<option value='"+z+"'>"+forecast_list[z].name+"</option>";
        }
        $(forecast_builder.list_element).html(out);

        // ------------------------------------------------------------
        forecast_builder.draw_table();
        // ------------------------------------------------------------
        
        // ADD FORECAST FROM FORECAST LIST
        $(forecast_builder.list_element).change(function(){
            var forecast_key = $(forecast_builder.list_element).val();
            var forecast_item = {"name":forecast_key,"weight":1.0};
            
            for (var z in forecast_list[forecast_key].params) {
                var default_val = "";
                if (forecast_list[forecast_key].params[z].default!=undefined) {
                    default_val = forecast_list[forecast_key].params[z].default;
                }
                forecast_item[z] = default_val;
            }
            
            forecast_builder.config.push(forecast_item);
            forecast_builder.draw_table();
            on_change_callback();
            setTimeout(function(){
                $(forecast_builder.list_element).val(0);
            },1000);
        });

        // REMOVE FORECAST FROM TABLE
        $(forecast_builder.table_element).on("click",".remove",function(){
            var fid = $(this).data("fid");
            forecast_builder.config.splice(fid,1);
            forecast_builder.draw_table();
            on_change_callback();
        });

        // APPLY FORECAST WEIGHT
        $(forecast_builder.table_element).on("change",".weight",function(){
            var forecast_id = $(this).data("fid");
            forecast_builder.config[forecast_id].weight = $(this).val()*1;
            on_change_callback();
        });
        
        // CONFIGURE FORECAST PARAMS
        $(forecast_builder.table_element).on("change",".param",function(){
            var forecast_id = $(this).data("fid");
            var param_key = $(this).data("param");
            var value = $(this).val();
            if (!isNaN(value)) value *= 1;
            forecast_builder.config[forecast_id][param_key] = value;
            on_change_callback();
        });
        
        on_change_callback();
    },

    draw_table: function() 
    {
        // Draw forecast configuration UI
        var out = "";
        // Foreach forecast in forecast_list
        for (var forecast_id in forecast_builder.config) {
            var forecast_key = forecast_builder.config[forecast_id].name;

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
                    if (forecast_builder.config[forecast_id][param_key]!=undefined) value = forecast_builder.config[forecast_id][param_key];
                    out += "<input class='param' type='text' data-param='"+param_key+"' data-fid="+forecast_id+" value='"+value+"' />";
                    
                // If dropdown selector
                } else if (forecast_list[forecast_key].params[param_key].type=="select") {
                    // each option in selector
                    var options = "";
                    for (var param_val in forecast_list[forecast_key].params[param_key].options) {
                        var selected = "";
                        if (forecast_builder.config[forecast_id][param_key]==param_val) selected = "selected";
                        options += "<option value='"+param_val+"' "+selected+">"+forecast_list[forecast_key].params[param_key].options[param_val]+"</option>";
                    }
                    out += "<select class='param' data-param='"+param_key+"' data-fid="+forecast_id+" style='margin-bottom:0'>"+options+"</select>";
                }
                out += "</div>";
            }
            out += "</td>";
            
            var value = "";
            if (forecast_builder.config[forecast_id].weight!=undefined) value = forecast_builder.config[forecast_id].weight;

            out += "<td><input class='weight' type='text' data-fid="+forecast_id+" value='"+value+"' />";
            out += "<td><i class='icon-trash remove' data-fid="+forecast_id+" style='cursor:pointer'></i></td>";
            out += "</tr>";
        }
        $(forecast_builder.table_element).html(out);
    }
}
