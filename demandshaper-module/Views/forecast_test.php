<?php global $path; ?>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/forecast_builder.js"></script>
<h3>Forecast Viewer</h3>

<div class="input-prepend input-append">
  <span class="add-on">Period</span>
  <input id="period" type="text" style="width:50px" />
  <span class="add-on">End</span>
  <input id="end" type="text" style="width:50px" />
</div>

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
  <tr><th>Forecast name</th><th>Parameters</th><th>Weight</th><th></th></tr>
  <tbody id="forecasts"></tbody>
</table>
<div class="input-prepend input-append"><span class="add-on">Add forecast</span><select id="forecast_list"></select></div>

<pre id="forecast_config_json"></pre>

<div id="placeholder_bound" style="width:100%; height:300px">
    <div id="placeholder" style="height:300px"></div>
</div>

<br>
<pre id="schedule_json"></pre>

<script>

var schedule_period = 3;
var schedule_end = 17;
$("#period").val(schedule_period);
$("#end").val(schedule_end);

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

// Note: forecast_list and forecast_config objects are in the global scope and are modified by the forecast_builder
forecast_builder.init("#forecasts","#forecast_list",forecast_config,function(){
    // fires on change of forecast config
    $("#forecast_config_json").html(JSON.stringify(forecast_config));
    localStorage.setItem("forecast_config",JSON.stringify(forecast_config));
    
    get_forecast(function(){
        schedule(function() {
            draw_graph();
        });
    });
});

// SCHEDULE PERIOD 
$("#period").change(function(){
    schedule_period = $(this).val()*1;
    schedule(function() {
        draw_graph();
    });
});
// SCHEDULE END
$("#end").change(function(){
    schedule_end = $(this).val()*1;
    schedule(function() {
        draw_graph();
    });
});

// FETCH COMBINED FORECAST 
function get_forecast(callback) {
    $.ajax({
        type: 'POST',
        data: "config="+JSON.stringify(forecast_config),
        url: path+"demandshaper/forecast", 
        dataType: 'json', 
        async: true, 
        success: function(result) {
            forecast = result;
            callback();
        }
    });
}

// FETCH CALCULATED SCHEDULE
function schedule(callback) {

    // Convert hour into end timestamp
    let end_hour = Math.floor(schedule_end);
    let end_minutes = (schedule_end - end_hour)*60;
    date = new Date();
    now = date.getTime()*0.001;
    date.setHours(end_hour,end_minutes,0,0);
    let end_timestamp = date.getTime()*0.001;
    if (end_timestamp<now) end_timestamp+=3600*24

    $.ajax({
        type: 'POST',
        data: "config="+JSON.stringify(forecast_config)+"&period="+(3600*schedule_period)+"&end="+end_timestamp,
        url: path+"demandshaper/schedule", 
        dataType: 'json', 
        async: true, 
        success: function(result) {
            periods = result;
            $("#schedule_json").html(JSON.stringify(periods));
            callback();
        }
    });
}

// DRAW FORECAST GRAPH
function draw_graph() {

    // Convert to flot format
    graph_profile = [];
    graph_active = [];
    
    var i=0;
    for (var time=forecast.start; time<forecast.end; time+=forecast.interval) {
    
        var active = false;
        for (var p in periods) {
            if (time>=periods[p].start[0] && time<periods[p].end[0]) active = true;
        } 
    
        if (active) {
            graph_active.push([time*1000,forecast.profile[i]]);
        } else {
            graph_profile.push([time*1000,forecast.profile[i]]); 
        }
        
        i++;
    }

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
        $.plot($('#placeholder'), [{data:graph_profile,color:"#aaa"},{data:graph_active,color:"#ea510e"}], options);
    }
}

</script>
