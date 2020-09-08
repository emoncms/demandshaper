var get_device_state_timeout = false;
var last_submit = 0;
var profile_graph_data = [];
var previousPoint = null;

$(document).ready(function() {
    update_input_UI();

    // Note: forecast_list and forecast_config objects are in the global scope and are modified by the forecast_builder
    forecast_builder.init("#forecasts","#forecast_list",schedule.settings.forecast_config,function(){
        
        // fires on change of forecast config    
        get_forecast(function(){
            calc_schedule(function() {
                save_schedule();
                update_output_UI();
            });
        });
    });

    var fn_name = schedule.settings.device_type+"_events";
    if (window[fn_name]!=undefined) window[fn_name]();

    update_UI_from_input_values();
    setInterval(update_UI_from_input_values,5000);
});
// -----------------------------------------------------------------------------------------------

// FETCH COMBINED FORECAST 
function get_forecast(callback) {
    $.ajax({
        type: 'POST',
        data: "config="+JSON.stringify(schedule.settings.forecast_config),
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
function calc_schedule(callback) {

    // Convert hour into end timestamp
    let end_hour = Math.floor(schedule.settings.end);
    let end_minutes = (schedule.settings.end - end_hour)*60;
    date = new Date();
    now = date.getTime()*0.001;
    date.setHours(end_hour,end_minutes,0,0);
    schedule.settings.end_timestamp = date.getTime()*0.001;
    if (schedule.settings.end_timestamp<now) schedule.settings.end_timestamp+=3600*24

    if (schedule.settings.ctrlmode=="smart") {
        
        $.ajax({
            type: 'POST',
            data: "config="+JSON.stringify(schedule.settings.forecast_config)+"&interruptible="+schedule.settings.interruptible+"&period="+(3600*schedule.settings.period)+"&end="+schedule.settings.end_timestamp,
            url: path+"demandshaper/schedule", 
            dataType: 'json', 
            async: true, 
            success: function(result) {
                schedule.runtime.periods = result;
                $("#schedule_json").html(JSON.stringify(schedule.runtime.periods));
                callback();
            }
        });
    
    } else {
        schedule.runtime.periods = [];
        callback();
    }
}

// -----------------------------------------------------------------------------------------------

// UPDATE CONTROLS UI 
// These are user interface items that are not the result of the schedule calculation
function update_input_UI() {

    $(".device_name").html(jsUcfirst(schedule.settings.device_name));
    $(".title-icon").html('<svg class="icon '+schedule.settings.device_type+'"><use xlink:href="#icon-'+schedule.settings.device_type+'"></use></svg>');
    $(".custom-name").html(" "+schedule.settings.device_name);
    $(".forecast_units").val(schedule.settings.forecast_units);

    // 1st row: Update ctrlmode buttons
    var ctrlmode_btn = $("#mode button[mode="+schedule.settings.ctrlmode+"]");
    ctrlmode_btn.addClass('active').siblings().removeClass('active');
    
    switch (schedule.settings.ctrlmode) {
      case "on":
        ctrlmode_btn.addClass("green").siblings().removeClass('red').removeClass('green');
        $(".smart").hide(); $(".timer").hide();
        break;
      case "off":
        ctrlmode_btn.addClass("red").siblings().removeClass('red').removeClass('green');
        $(".smart").hide(); $(".timer").hide();
        break;
      case "timer":
        ctrlmode_btn.siblings().removeClass('red').removeClass('green');
        $(".smart").hide(); $(".timer").show();
        break;
      case "smart":
        ctrlmode_btn.siblings().removeClass('red').removeClass('green');
        $(".smart").show(); $(".timer").hide();
        break;
    }
    
    // 2nd row: schedule controls
    $("#period input[type=time]").val(timestr(schedule.settings.period,false));
    $("#end input[type=time]").val(timestr(schedule.settings.end,false));
    $(".scheduler-checkbox[name='interruptible']").attr("state",schedule.settings.interruptible);
    
    var fn_name = "update_input_UI_"+schedule.settings.device_type;
    if (window[fn_name]!=undefined) window[fn_name]();
    
    $(".device_name").val(schedule.settings.device_name);
    $("#ip_address").html("IP Address: <a href='http://"+schedule.settings.ip+"'>"+schedule.settings.ip+"</a>");
}

function update_UI_from_input_values(){
    $.ajax({ url: path+"input/get/"+schedule.settings.device, dataType: 'json', async: true, success: function(inputs) {
        if (inputs!=null) {
            var fn_name = schedule.settings.device_type+"_update_UI_from_input_values";
            if (window[fn_name]!=undefined) window[fn_name](inputs);
        }
    }});
}

// UPDATE OUTPUT ELEMENTS
// These are user interface items that are the result of the schedule calculation
function update_output_UI() {

    // Output schedule periods
    var periods = [];
    for (var z in schedule.runtime.periods) {
    
        date = (new Date(schedule.runtime.periods[z].start[0]*1000));
        let h1 = date.getHours();
        let m1 = date.getMinutes()/60;

        date = (new Date(schedule.runtime.periods[z].end[0]*1000));
        let h2 = date.getHours();
        let m2 = date.getMinutes()/60;
                
        periods.push(timestr(h1+m1,true)+" to "+timestr(h2+m2,true));
    }
    var out = ""; 
    if (periods.length) {
        out = jsUcfirst(schedule.settings.device_name)+" scheduled to run: <b>"+periods.join(", ")+"</b><br>";
    }
    $("#schedule-output").html(out);
    $("#timeleft").html(Math.round(schedule.runtime.timeleft/60)+" mins left to run");

    // Used for description below graph
    var sum = 0;
    var sum_n = 0;
    var peak = 0;
    var lowest = 0;
        
    // Convert to flot format
    graph_profile = [];
    graph_active = [];
    
    var i=0;
    for (var time=forecast.start; time<forecast.end; time+=forecast.interval) {

        var value = forecast.profile[i];
        if (value>peak) peak = value;
        if (value<lowest) lowest = value;

        var active = false;
        for (var p in schedule.runtime.periods) {
            if (time>=schedule.runtime.periods[p].start[0] && time<schedule.runtime.periods[p].end[0]) active = true;
        } 

        if (schedule.settings.ctrlmode=="on") active = true;
    
        if (active) {
            graph_active.push([time*1000,forecast.profile[i]]);
            sum += value; sum_n++;
        } else {
            graph_profile.push([time*1000,forecast.profile[i]]); 
        }
        
        i++;
    }
    
    profile_graph_data = [{data:graph_profile,color:"#aaa"},{data:graph_active,color:"#ea510e"}];
    draw_profile_graph();
   
    var mean = 0;
    if (sum_n>0) mean = sum/sum_n;
    
    console.log(schedule.settings.forecast_units)
   
    var fn_name = "schedule_info_"+schedule.settings.device_type;
    if (window[fn_name]!=undefined) {
        window[fn_name](schedule.settings.forecast_units,mean,peak);
    } else {
        if (schedule.settings.forecast_units=="gco2") {
            $("#schedule-info").html("CO2 intensity: "+Math.round(mean)+" gCO2/kWh, "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
        } else if (schedule.settings.forecast_units=="pkwh") {
            $("#schedule-info").html("Average cost: "+mean.toFixed(1)+"p/kWh, "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
        } else {
            $("#schedule-info").html("Average: "+mean.toFixed(1)+", "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
        }
    }
}

function draw_profile_graph(){

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
        $.plot($('#placeholder'), profile_graph_data, options);
    }
}

function save_schedule() {
    
    last_submit = (new Date()).getTime();
    setTimeout(function(){
        if (((new Date()).getTime()-last_submit)>1900) {
            $.ajax({
                type: 'POST',
                data: "schedule="+JSON.stringify(schedule),
                url: path+"demandshaper/save", 
                dataType: 'text', 
                async: true, 
                success: function(result) {
                    clearTimeout(get_device_state_timeout)
                    get_device_state_timeout = setTimeout(function(){ get_device_state(); },1000);
                }
            });
        }
    },2000);
}

function on_UI_change() {
    update_input_UI();
    calc_schedule(function() {
        save_schedule();
        update_output_UI();
    });
}

$(window).resize(function(){
    draw_profile_graph();
});

// -----------------------------------------------------------------------------------------------

$("#mode button").click(function() {
    schedule.settings.ctrlmode = $(this).attr("mode");
    on_UI_change();
});

$(".device_name").change(function() {
    schedule.settings.device_name = $(this).val();
    on_UI_change();
});

$(".input-time input[type=time]").change(function() {
    
    var name = $(this).parent().attr("id");
    var timestring = $(this).val();
    var parts = timestring.split(":");
    var hour = parseInt(parts[0])+(parseInt(parts[1])/60.0)
    schedule.settings[name] = hour
    
    if (name=="period") schedule.runtime.timeleft = schedule.settings.period * 3600;
    
    on_UI_change();
});

$(".input-time button").click(function() {
    
    var name = $(this).parent().attr("id");
    var type = $(this).html();
    var resolution_hours = 0.5;
    
    if (type=="+") {
        schedule.settings[name] += resolution_hours;
        if (schedule.settings[name]>(24.0-resolution_hours)) schedule.settings[name] = 0.0;    
    } else if (type=="-") {
        schedule.settings[name] -= resolution_hours;
        if (schedule.settings[name]<0.0) schedule.settings[name] = 24.0-resolution_hours;
    }
    
    if (name=="period") schedule.runtime.timeleft = schedule.settings.period * 3600;
    
    on_UI_change();
});

$(".scheduler-checkbox").click(function(){
    var name = $(this).attr('name');
    var state = 1; if ($(this).attr('state')==true) state = 0;
    $(this).attr("state",state);
    schedule.settings[name] = state;
    
    on_UI_change();
});

$(".forecast_units").change(function() {
    schedule.settings.forecast_units = $(this).val();
    on_UI_change();
});

$(".config-device").click(function(){
    //$(".scheduler-controls").toggle();
    $(".scheduler-config").parent().toggle();
});

$("#placeholder").bind("plothover", function (event, pos, item) {
    if (item) {
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;
            $("#tooltip").remove();
            tooltip(item.pageX+40,item.pageY,item.datapoint[1], "#DDDDDD");
        }
    } else {
        $("#tooltip").remove();
        previousPoint = null;
    }
});

function tooltip(x, y, contents, bgColour)
{
    var offset = 15; // use higher values for a little spacing between `x,y` and tooltip
    var elem = $('<div id="tooltip">' + contents + '</div>').css({
        position: 'absolute',
        display: 'none',
        'font-weight':'bold',
        border: '1px solid rgb(255, 221, 221)',
        padding: '2px',
        'background-color': bgColour,
        opacity: '0.8'
    }).appendTo("body").fadeIn(200);

    var elemY = y - elem.height() - offset;
    var elemX = x - elem.width()  - offset;
    if (elemY < 0) { elemY = 0; } 
    if (elemX < 0) { elemX = 0; } 
    elem.css({
        top: elemY,
        left: elemX
    });
};

// MISC FUNCTIONS
function timestr(hour,type){
    
    var h = Math.floor(hour);
    var m = Math.round((hour - h) * 60);
    if (h<10) h = "0"+h;
    if (m<10) m = "0"+m;
    var str = h+":"+m;
    
    if (type) {
        if (hour==0) str = "Midnight";
        else if (hour==12) str = "Noon";
        else if (h>12) {
            h = h - 12;
            str = h+":"+m+" pm";
        } else if (h<12) {
            str += "am";
        }
    }
    return str;
}

function jsUcfirst(string) {return string.charAt(0).toUpperCase() + string.slice(1);}
