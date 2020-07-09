function update_input_UI_openevse() {
    $(".openevse").show();

    if (schedule.settings.soc_source=="input" || schedule.settings.soc_source=="ovms") {
        $("#run_period").hide();
        $("#run_period").parent().addClass('span2').removeClass('span4');
        $(".openevse-balancing").show();
        $("#battery_bound").show();
        if (schedule.settings.soc_source=="ovms") $(".ovms-options").show();

        battery.capacity = schedule.settings.battery_capacity
        battery.charge_rate = schedule.settings.charge_rate
        battery.target_soc = schedule.settings.target_soc
        battery.soc = schedule.settings.current_soc
        battery.balpercentage = schedule.settings.balpercentage
        battery.baltime = schedule.settings.baltime
        battery.init("battery");
        battery.draw();
        
    } else {
        $("#run_period").show();
        $("#run_period").parent().addClass('span4').removeClass('span2');
        $(".openevse-balancing").hide(); 
        $("#battery_bound").hide(); 
        $(".ovms-options").hide();
    }
    
    $(".input[name=soc_source]").val(schedule.settings.soc_source);
    $(".input[name=battery_capacity]").val(schedule.settings.battery_capacity);
    $(".input[name=charge_rate]").val(schedule.settings.charge_rate);
    $(".input[name=balpercentage]").val(schedule.settings.balpercentage*100);
    $(".input[name=baltime]").val(schedule.settings.baltime*60);
    $(".input[name=ovms_vehicleid]").val(schedule.settings.ovms_vehicleid);
    $(".input[name=ovms_carpass]").val(schedule.settings.ovms_carpass);
}

function schedule_info_openevse(signal_type,mean,peak) {
    switch(signal_type) {
      case "co2":
        var co2_km = (mean / 4.0) / 1.6;
        var prc = 100-(100*(co2_km / 130));
        $("#schedule-info").html("Charge CO2 intensity: "+Math.round(mean)+" gCO2/kWh, "+Math.round(co2_km)+" gCO2/km, "+Math.round(prc)+"% <span title='Compared to 50 MPG Petrol car'>reduction</span>.");
        break;
      case "price":
        var p_per_mile = (mean / 4.0);
        var prc = 100-(100*(p_per_mile / 10.0));
        $("#schedule-info").html("Cost: "+mean.toFixed(1)+"p/kWh, "+(p_per_mile).toFixed(1)+"p/mile, "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
        break;
      default:
        $("#schedule-info").html("Average: "+mean.toFixed(1)+", "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
    }
}

function openevse_update_UI_from_input_values(inputs) {
    if (inputs.amp!=undefined) $("#charge_current").html((inputs.amp.value*0.001).toFixed(1));
    if (inputs.temp1!=undefined) $("#openevse_temperature").html((inputs.temp1.value*0.1).toFixed(1));

    if (schedule.settings.soc_source=='input' && inputs.soc!=undefined) {
        var last_soc = parseFloat(schedule.settings.current_soc)
        schedule.settings.current_soc = inputs.soc.value*0.01;
        schedule.settings.period = ((schedule.settings.target_soc-schedule.settings.current_soc)*schedule.settings.battery_capacity)/schedule.settings.charge_rate;
        if (schedule.settings.current_soc!=last_soc) on_UI_change();
    }
}

function openevse_fetch_ovms_soc(callback) {
    if (schedule.settings.ovms_vehicleid!='' && schedule.settings.ovms_carpass!='') {
        $.ajax({ 
            url: path+"demandshaper/ovms?vehicleid="+schedule.settings.ovms_vehicleid+"&carpass="+schedule.settings.ovms_carpass, 
            dataType: 'json', async: true, success: function(ovms_result){
                var last_soc = parseFloat(schedule.settings.current_soc)                        
                schedule.settings.current_soc = ovms_result.soc*0.01;
                schedule.settings.period = ((schedule.settings.target_soc-schedule.settings.current_soc)*schedule.settings.battery_capacity)/schedule.settings.charge_rate;
                
                var changed = false;
                if (schedule.settings.current_soc!=last_soc) changed = true;
                callback(changed);      
            }
        });
    }
}

function openevse_events() {
    // Load current SOC at startup
    if (schedule.settings.soc_source=="ovms") {
        openevse_fetch_ovms_soc(function(changed){
            if (changed) on_UI_change();
        });
    }

    $("#battery").on("bchange",function() {
        schedule.settings.period = battery.period
        schedule.settings.target_soc = battery.target_soc
        schedule.runtime.timeleft = schedule.settings.period * 3600;
        on_UI_change();
    });  

    $('.input[name="soc_source"]').change(function(){
        schedule.settings.soc_source =  $(this).val();
        
        if (schedule.settings.soc_source=="ovms") {
            openevse_fetch_ovms_soc(function(changed){
                on_UI_change();
            });
        } else {
            on_UI_change();
        }
    });
    
    $('.input[name="battery_capacity"]').change(function(){
        var battery_capacity = $(this).val();
        schedule.settings.battery_capacity = battery_capacity*1.0;
        if (schedule.settings.battery_capacity<0.0) schedule.settings.battery_capacity = 0.0;
        on_UI_change();
    });

    $('.input[name="charge_rate"]').change(function(){
        var charge_rate = $(this).val();
        schedule.settings.charge_rate = charge_rate*1.0;
        if (schedule.settings.charge_rate<0.0) schedule.settings.charge_rate = 0.0;
        on_UI_change();
    });
    
    $('.input[name="ovms_vehicleid"]').change(function(){
        schedule.settings.ovms_vehicleid = $(this).val();
        openevse_fetch_ovms_soc(function(changed){
            on_UI_change();
        });
    });

    $('.input[name="ovms_carpass"]').change(function(){
        schedule.settings.ovms_carpass = $(this).val();
        openevse_fetch_ovms_soc(function(changed){
            on_UI_change();
        });
    });

    $('.input[name="balpercentage"]').change(function(){
        var balpercentage = $(this).val();
        schedule.settings.balpercentage = (balpercentage * 0.01);
        if (schedule.settings.balpercentage<0.0) schedule.settings.balpercentage = 0.0;
        if (schedule.settings.balpercentage>1.0) schedule.settings.balpercentage = 1.0;
        on_UI_change();
    });

    $('.input[name="baltime"]').change(function(){
        var baltime = $(this).val();
        schedule.settings.baltime = baltime / 60;
        if (schedule.settings.baltime<0.0) schedule.settings.baltime = 0.0;
        if (schedule.settings.baltime>24.0) schedule.settings.baltime = 24.0;
        on_UI_change();
    });
}
