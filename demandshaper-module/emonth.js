var temperature_feed = false;
var humidity_feed = false;

function load_device(device_id, device_name, device_type)
{
    device_loaded = true;
    console.log("device loaded");

    $("#devicename").html(jsUcfirst(device_name));
    $(".node-scheduler-title").html("<span class='icon-"+device_type+"'></span>"+device_name);
    $(".node-scheduler").attr("node",device_name);
    
    first_load = true;
    update_status();
    setInterval(update_status,5000);
    function update_status(){
        $.ajax({ url: emoncmspath+"input/get/"+device_name+apikeystr, dataType: 'json', async: true, success: function(result) {
            if (result!=null) {
                if (result.temperature!=undefined && result.humidity!=undefined) {
                    if (result.temperature.value!=null && result.humidity.value!=null) {
                        $("#emonth_temperature").html((result.temperature.value).toFixed(1));
                        $("#emonth_humidity").html((result.humidity.value).toFixed(1));
                        
                        var processList = (result.temperature.processList).split(",");
                        var processListItem = (processList[0]).split(":");
                        if (processListItem[0]=="1") temperature_feed = 1*processListItem[1];

                        var processList = (result.humidity.processList).split(",");
                        var processListItem = (processList[0]).split(":");
                        if (processListItem[0]=="1") humidity_feed = 1*processListItem[1];
                        
                        // console.log(temperature_feed);
                        // console.log(humidity_feed);
                        
                        if (first_load) {
                            first_load = false;
                            load_graph();
                        }
                    }
                }
            }
        }});
    }
    
    $(window).resize(function(){
        draw_graph();
    });
}

function load_graph() {

    end = +new Date;
    start = end - (3600000*24.0*1);

    var interval = 60;
    temperature_data = []
    humidity_data = []
    $.ajax({                                      
        url: emoncmspath+"feed/data.json?ids="+temperature_feed+","+humidity_feed+"&start="+start+"&end="+end+"&interval="+interval+apikeystr,
        dataType: 'json',
        async: true,                      
        success: function(result) {
            temperature_data = result[0].data;
            humidity_data = result[1].data;
            draw_graph();
            
            // -----------------------------------------
            // Temperature min/max/mean
            // -----------------------------------------
            var T_min = 100;
            var T_max = -100;
            var T_sum = 0;
            var n = 0;
            
            for (var z in temperature_data) {
                var T = temperature_data[z][1];
                if (T<T_min) T_min = T;
                if (T>T_max) T_max = T;
                T_sum += T;
                n++;
            }
            T_mean = T_sum / n;
            
            $("#emonth_temperature_min").html((T_min).toFixed(1));
            $("#emonth_temperature_max").html((T_max).toFixed(1));
            $("#emonth_temperature_mean").html((T_mean).toFixed(1));
            
            // -----------------------------------------
            // Humidity min/max/mean
            // -----------------------------------------
            var H_min = 100;
            var H_max = -100;
            var H_sum = 0;
            var n = 0;
            
            for (var z in humidity_data) {
                var H = humidity_data[z][1];
                if (H<H_min) H_min = H;
                if (H>H_max) H_max = H;
                H_sum += H;
                n++;
            }
            H_mean = H_sum / n;
            
            $("#emonth_humidity_min").html((H_min).toFixed(1));
            $("#emonth_humidity_max").html((H_max).toFixed(1));
            $("#emonth_humidity_mean").html((H_mean).toFixed(1));
            // -----------------------------------------
        }
    });
}

function draw_graph() {
    var flot_font_size = 12;
    var options = {
        xaxis: { 
            mode: "time", 
            timezone: "browser", 
            font: {size:flot_font_size, color:"#666"}, 
            // labelHeight:-5
            reserveSpace:false,
            min: start,
            max: end
        },
        yaxis: { 
            font: {size:flot_font_size, color:"#666"}, 
            // labelWidth:-5
            reserveSpace:false
        },
        selection: { mode: "x" },
        grid: {
            show:true, 
            color:"#aaa",
            borderWidth:0,
            hoverable: true, 
            clickable: true
        }
    }
    
    var width = $("#placeholder_bound").width();
    if (width>0) {
        $("#placeholder").width(width);
        $.plot($('#placeholder'), [{data:temperature_data,color:"#e14040",yaxis:1},{data:humidity_data,color:"#4072e1",yaxis:2}], options);
    }
}



function jsUcfirst(string) {return string.charAt(0).toUpperCase() + string.slice(1);}
