function openevse_update_ui() {
    $(".openevse").show();

    $('.input[name="openevsecontroltype"]').val(schedule.settings.openevsecontroltype);
    $('.input[name="batterycapacity"]').val(schedule.settings.batterycapacity);
    $('.input[name="chargerate"]').val(schedule.settings.chargerate);
    $('.input[name="balpercentage"]').val(schedule.settings.balpercentage * 100);
    $('.input[name="baltime"]').val(Math.round(schedule.settings.baltime * 60));

    if (schedule.settings.openevsecontroltype=="socinput" || schedule.settings.openevsecontroltype=="socovms") {

        battery.capacity = schedule.settings.batterycapacity;
        battery.charge_rate = schedule.settings.chargerate;
        battery.end_soc = schedule.settings.ev_target_soc;
        battery.soc = schedule.settings.ev_soc;
        battery.balpercentage = schedule.settings.balpercentage;
        battery.baltime = schedule.settings.baltime;

        $("#battery_bound").show();
        battery.init("battery");
        battery.draw();
        
        $("#run_period").hide();
        $("#run_period").parent().addClass('span2').removeClass('span4');
        $(".openevse-balancing").show();
    } else {
        $("#battery_bound").hide();
        $("#run_period").show();
        $("#run_period").parent().addClass('span4').removeClass('span2');
        $(".openevse-balancing").hide();
    }

    if (schedule.settings.openevsecontroltype=="socovms") {
        $(".ovms-options").show();
        $('.input[name="vehicleid"]').val(schedule.settings.ovms_vehicleid);
        $('.input[name="carpass"]').val(schedule.settings.ovms_carpass);
    } else {
        $(".ovms-options").hide();
    }
}


function openevse_events() {

    $("#battery").on("bchange",function() { 
        battery_change();
    });
    
    function battery_change() {
        console.log("battery_change");
        
        battery.period = Math.round(battery.period/resolution_hours)*resolution_hours
        schedule.settings.period = battery.period
        schedule.settings.ev_target_soc = battery.end_soc
        schedule.runtime.timeleft = schedule.settings.period * 3600;
        
        if (mode=="on") {
            var now = new Date();
            var now_hours = (now.getHours() + (now.getMinutes()/60));
            schedule.settings.end = Math.round((now_hours+schedule.settings.period)/resolution_hours)*resolution_hours;
        }
        calc_schedule();
    }   

    $('.input[name="openevsecontroltype"]').change(function(){
        schedule.settings.openevsecontroltype =  $(this).val();
        calc_schedule();
    });
    
    $('.input[name="batterycapacity"]').change(function(){
        var batterycapacity = $(this).val();
        schedule.settings.batterycapacity = batterycapacity*1.0;
        if (schedule.settings.batterycapacity<0.0) schedule.settings.batterycapacity = 0.0;
        calc_schedule();
    });

    $('.input[name="chargerate"]').change(function(){
        var chargerate = $(this).val();
        schedule.settings.chargerate = chargerate*1.0;
        if (schedule.settings.chargerate<0.0) schedule.settings.chargerate = 0.0;
        calc_schedule();
    });
    
    $('.input[name="vehicleid"]').change(function(){
        var vehicleid = $(this).val();
        schedule.settings.ovms_vehicleid = vehicleid;
        calc_schedule();
    });

    $('.input[name="carpass"]').change(function(){
        var carpass = $(this).val();
        schedule.settings.ovms_carpass = carpass;
        calc_schedule();
    });

    $('.input[name="balpercentage"]').change(function(){
        var balpercentage = $(this).val();
        schedule.settings.balpercentage = (balpercentage * 0.01);
        if (schedule.settings.balpercentage<0.0) schedule.settings.balpercentage = 0.0;
        if (schedule.settings.balpercentage>1.0) schedule.settings.balpercentage = 1.0;
        calc_schedule();
    });

    $('.input[name="baltime"]').change(function(){
        var baltime = $(this).val();
        schedule.settings.baltime = baltime / 60;
        if (schedule.settings.baltime<0.0) schedule.settings.baltime = 0.0;
        if (schedule.settings.baltime>24.0) schedule.settings.baltime = 24.0;
        calc_schedule();
    });
}
