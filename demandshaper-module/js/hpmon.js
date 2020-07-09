function update_input_UI_hpmon() {
    $(".heatpumpmonitor").show();
    
    if (schedule.settings.flowT!=undefined) {
        $("#flowT input[type=text]").val(schedule.settings.flowT.toFixed(1)+"C");
    }
}

function hpmon_update_UI_from_input_values(inputs) {
    
    if (inputs.SNXflowT!=undefined && inputs.SNXflowT.value!=null) {
         $("#heatpump_flowT").html((inputs.SNXflowT.value).toFixed(1));
         if (schedule.settings.flowT==undefined) {
             schedule.settings.flowT = inputs.SNXflowT.value;
             $("#flowT input").val(inputs.SNXflowT.value.toFixed(1)+"C");
         }
    }
    
    if (inputs.SNXheat!=undefined && inputs.SNXheat.value!=null) {
        $("#heatpump_heat").html((inputs.SNXheat.value).toFixed(0));
    }
}

function schedule_info_hpmon(signal_type,mean,peak) {
    switch(signal_type) {
      case "co2":
        $("#schedule-info").html("CO2 intensity: "+Math.round(mean)+" gCO2/kWh, "+Math.round(mean/3.8)+" gCO2/kWh Heat @ COP 3.8");
        break;
      case "price":
        $("#schedule-info").html(mean.toFixed(1)+" p/kWh, "+(mean/3.8).toFixed(1)+" p/kWh Heat @ COP 3.8"); 
        break;
      default:
        $("#schedule-info").html("Average: "+mean.toFixed(1)+", "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak");
    }
}

function hpmon_events() {
    $(".input-temperature button").click(function() {
        var name = $(this).parent().attr("id");
        var type = $(this).html();
        
        if (type=="+") {
            schedule.settings[name] += 1.0;
        } else if (type=="-") {
            schedule.settings[name] -= 1.0;
        }
        on_UI_change();
    });

    $(".input-temperature input[type=text]").change(function() {
        var name = $(this).parent().attr("id");
        var tempstr = $(this).val();
        tempstr = tempstr.replace("C","");
        schedule.settings[name] = parseFloat(tempstr);
        on_UI_change();
    });
}
