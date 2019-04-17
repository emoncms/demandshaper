var temperature_feed = false;
var humidity_feed = false;

function load_device() {


    if (device!=undefined && devices[device]!=undefined) {
        device_loaded = true;
        console.log("device loaded");

        $("#devicename").html(jsUcfirst(device));
        $(".node-scheduler-title").html("<span class='icon-"+devices[device].type+"'></span>"+device);
        $(".node-scheduler").attr("node",device);
        
        first_load = true;
        update_status();
        setInterval(update_status,5000);
        function update_status(){
            $.ajax({ url: emoncmspath+"input/get/"+device+apikeystr, dataType: 'json', async: true, success: function(result) {
                if (result!=null) {
                    if (result.temperature!=undefined && result.humidity!=undefined) {
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
            }});
        }
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
    $.ajax({                                      
        url: path+"feed/average.json?id="+temperature_feed+"&start="+start+"&end="+end+"&interval="+interval+apikeystr,
        dataType: 'json',
        async: false,                      
        success: function(result) {
            temperature_data = result;
        }
    });
    
    humidity_data = []
    $.ajax({                                      
        url: path+"feed/average.json?id="+humidity_feed+"&start="+start+"&end="+end+"&interval="+interval+apikeystr,
        dataType: 'json',
        async: false,                      
        success: function(result) {
            humidity_data = result;
        }
    });
    
    draw_graph();
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
