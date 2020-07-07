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
