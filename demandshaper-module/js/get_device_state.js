// --------------------------------------------------------------------------------------------
// Fetches the device state via http request
// used to provide user feedback to confirm that schedule has been transfered to device
// --------------------------------------------------------------------------------------------   
function get_device_state() {
    $.ajax({ url: path+"demandshaper/get-state?device="+schedule.settings.device_name,
        dataType: 'json',
        async: true,
        success: function(result) {
        
            if (result==false) {
                console.log("Unresponsive");
                $(".device-state-message").html("Unresponsive");
                $(".node-scheduler-title").css("background-color","#bbb");
                $(".node-scheduler").css("background-color","#bbb");
                
                clearTimeout(get_device_state_timeout)
                get_device_state_timeout = setTimeout(function(){ get_device_state(); },5000);
            } else {
                state_matched = true;
                
                if (result.ctrl_mode!=undefined) {
                    device_ctrl_mode = result.ctrl_mode.toLowerCase();
                } else {
                    device_ctrl_mode = "timer";
                }
                if (schedule.settings.ctrlmode!=device_ctrl_mode) state_matched = false;
                if (schedule.settings.ctrlmode=="smart" && device_ctrl_mode=="timer") state_matched = true;
                if (!state_matched) console.log(schedule.settings.ctrlmode+"!="+device_ctrl_mode)
                
                if (schedule.runtime.periods==undefined) schedule.runtime.periods = []
                
                if (schedule.settings.ctrlmode=="smart" || schedule.settings.ctrlmode=="timer") {
                    if (schedule.runtime.periods.length>0) {
                        if (schedule.runtime.periods[0].start[1].toFixed(3) != result.timer_start1.toFixed(3)) { 
                            state_matched = false; 
                            console.log(schedule.runtime.periods[0].start[1].toFixed(3)+"!="+result.timer_start1.toFixed(3)); 
                        }
                        if (schedule.runtime.periods[0].end[1].toFixed(3) != result.timer_stop1.toFixed(3)) { 
                            state_matched = false; 
                            console.log(schedule.runtime.periods[0].end[1].toFixed(3)+"!="+result.timer_stop1.toFixed(3)); 
                        }
                    }
                }
                
                if (schedule.settings.device_type=="hpmon") {
                    schedule_voltage_output = Math.round((schedule.settings.flowT - 7.14)/0.0371);
                    if (schedule_voltage_output != result.voltage_output) state_matched = false;
                }
                
                if (state_matched) {
                    console.log("device state matched");
                    $(".device-state-message").html("Saved");
                    setTimeout(function(){
                        $(".device-state-message").html("");
                    },2000);
                    $(".node-scheduler-title").css("background-color","#ea510e");
                    $(".node-scheduler").css("background-color","#ea510e");
                } else {
                    console.log("device state mismatch");
                    $(".node-scheduler-title").css("background-color","#bbb");
                    $(".node-scheduler").css("background-color","#bbb");
                    $(".device-state-message").html("Settings Mismatch");
                    clearTimeout(get_device_state_timeout)
                    get_device_state_timeout = setTimeout(function(){ get_device_state(); },5000);
                }
            }
        }
    });        
}
