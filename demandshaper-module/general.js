function load_device()
{
    if (device!=undefined && devices[device]!=undefined) {
        device_loaded = true;
        console.log("device loaded");

        $("#no-devices-found").hide();
        $("#scheduler-outer").show();

        $("#devicename").html(jsUcfirst(device));
        $(".node-scheduler-title").html("<span class='icon-"+devices[device].type+"'></span>"+device);
        $(".node-scheduler").attr("node",device);

        if (devices[device].type=="openevse") {
            var vehicleid = "";
            var carpass = "";
            var current_soc = 0.2;
            $.ajax({ url: emoncmspath+"demandshaper/ovms?vehicleid="+vehicleid+"&carpass="+carpass, dataType: 'json', async: false, success: function(result) {
                current_soc = result.soc*0.01;
            }});
        
            $("#openevse").show();
            battery.init("battery");
            battery.draw(current_soc,0.8);
            battery.events();
            
            $("#run_period").hide();
            $("#run_period").parent().addClass('span2').removeClass('span4');    
        }

        if (devices[device].type=="hpmon") {
            $(".heatpumpmonitor").show();
        }

        // -------------------------------------------------------------------------
        // Defaults
        // -------------------------------------------------------------------------
        var default_schedule = {
            device:device,
            device_type:devices[device].type,
            ctrlmode:"smart", // on, off, smart, timer
            signal:"cydynni",
            timeleft:0,
            // Smart mode
            period:3,
            end:8,
            interruptible:0,
            // Timer mode
            timer_start1:0,
            timer_stop1:0,
            timer_start2:0,
            timer_stop2:0,
            // Repetition
            repeat:[1,1,1,1,1,1,1],
            runonce:false,
            // hpmon
            flowT:30.0
        };

        var schedule = default_schedule;
        var last_period = schedule.period;

        var options = {
            xaxis: { 
                mode: "time", 
                timezone: "browser", 
                font: {size:12, color:"#666"}, 
                reserveSpace:false
            },
            yaxis: { 
                font: {size:12, color:"#666"}, 
                reserveSpace:false,
                min:0
            },
            grid: {
                show:true, 
                color:"#aaa",
                borderWidth:0,
                hoverable: true, 
                clickable: true
            },
            selection: { mode: "x" },
            touch: { pan: "x", scale: "x" }
        };

        // -------------------------------------------------------------------------
        // Fetch device schedule, init view
        // -------------------------------------------------------------------------
        $.ajax({ url: emoncmspath+"demandshaper/get?device="+device+apikeystr, dataType: 'json', async: true, success: function(result) {
            // Make schedule object global
            if (result==null || result.schedule==null) {
                // invalid schedule, use default applied above        
            } else {
                schedule = default_schedule;
                for (var z in default_schedule) {
                    if (result.schedule[z]!=undefined) schedule[z] = result.schedule[z];
                }
                
                schedule.device = device;
                schedule.device_type = devices[device].type;
            }
            calc_schedule();
        }});

        update_status();
        setInterval(update_status,5000);
        function update_status(){
            $.ajax({ url: emoncmspath+"input/get/"+device+apikeystr, dataType: 'json', async: true, success: function(result) {
                if (result!=null) {
                    if (result.amp!=undefined) $("#charge_current").html((result.amp.value*0.001).toFixed(1));
                    if (result.temp1!=undefined) $("#openevse_temperature").html((result.temp1.value*0.1).toFixed(1));
                    if (result.SNXflowT!=undefined) {
                         $("#heatpump_flowT").html((result.SNXflowT.value).toFixed(1));
                         if (schedule.flowT==undefined) {
                             schedule.flowT = result.SNXflowT.value;
                             $("#flowT input").val(result.SNXflowT.value.toFixed(1)+"C");
                         }
                    }
                    if (result.SNXheat!=undefined) $("#heatpump_heat").html((result.SNXheat.value).toFixed(0));
                }
            }});
        }

        // -------------------------------------------------------------------------
        // Events
        // -------------------------------------------------------------------------

        $("#mode button").click(function() {
            schedule.ctrlmode = $(this).attr("mode");
            $(this).addClass('active').siblings().removeClass('active');
            
            switch (schedule.ctrlmode) {
              case "on":
                // if (schedule.period==0) schedule.period = last_period;
                // var now = new Date();
                // var now_hours = (now.getHours() + (now.getMinutes()/60));
                // schedule.end = Math.round((now_hours+schedule.period)/0.5)*0.5;
                $(this).addClass("green").siblings().removeClass('red').removeClass('green');
                break;
              case "off":
                last_period = schedule.period;
                schedule.period = 0;
                schedule.end = 0;
                $(this).addClass("red").siblings().removeClass('red').removeClass('green');
                break;
              case "timer":
                // if (schedule.period==0) schedule.period = last_period;
                // if (schedule.end==0) schedule.end = 8.0;
                $(this).siblings().removeClass('red').removeClass('green');
                break;
              case "smart":
                $(this).siblings().removeClass('red').removeClass('green');
                break;
            }
            calc_schedule();
        });

        $(".input-time button").click(function() {
            //schedule.ctrlmode = "smart";
            //$("#mode button[mode=smart]").addClass('active').siblings().removeClass('active');
            
            var name = $(this).parent().attr("id");
            var type = $(this).html();
            
            if (type=="+") {
                schedule[name] += 0.5;
                if (schedule[name]>23.5) schedule[name] = 0.0;    
            } else if (type=="-") {
                schedule[name] -= 0.5;
                if (schedule[name]<0.0) schedule[name] = 23.5;
            }
            calc_schedule();
        });

        $(".input-time input[type=time]").change(function() {
            //schedule.ctrlmode = "smart";
            //$("#mode button[mode=smart]").addClass('active').siblings().removeClass('active');
            
            var name = $(this).parent().attr("id");
            var timestr = $(this).val();
            
            var parts = timestr.split(":");
            schedule[name] = parseInt(parts[0])+(parseInt(parts[1])/60.0)
            calc_schedule(); 
        });

        $(".input-temperature button").click(function() {
            var name = $(this).parent().attr("id");
            var type = $(this).html();
            
            if (type=="+") {
                schedule[name] += 1.0;
            } else if (type=="-") {
                schedule[name] -= 1.0;
            }
            calc_schedule();
        });

        $(".input-temperature input[type=text]").change(function() {
            var name = $(this).parent().attr("id");
            var tempstr = $(this).val();
            tempstr = tempstr.replace("C","");
            schedule[name] = parseFloat(tempstr);
            calc_schedule(); 
        });

        $(".scheduler-checkbox").click(function(){
            var name = $(this).attr('name');
            var state = 1; if ($(this).attr('state')==true) state = 0;
            
            $(this).attr("state",state);
            schedule[name] = state;
            calc_schedule();
        });

        $(".weekly-scheduler").click(function(){
            var day = $(this).attr('day');
            var val = 1; if ($(this).attr('val')==1) val = 0;
            
            $(this).attr("val",val);
            schedule.repeat[day] = val;
            calc_schedule();
        });

        $("#battery").on("bchange",function() { 
            schedule.period = battery.period;
            
            if (mode=="on") {
                var now = new Date();
                var now_hours = (now.getHours() + (now.getMinutes()/60));
                schedule.end = Math.round((now_hours+schedule.period)/0.5)*0.5;
            }
            calc_schedule();
        });

        $(".scheduler-select").change(function(){
            var name = $(this).attr('name');
            schedule[name] = $(this).val();
            calc_schedule();
        });

        $(".delete-device").click(function(){
            $("#DeleteDeviceModal").modal();
            $(".device-name").html(schedule.device);
        });

        $("#delete-device-confirm").click(function(){
            schedule.ctrlmode = "off";
            calc_schedule();
            // 1. Delete device
            $.ajax({ url: path+"device/delete.json", data: "id="+devices[schedule.device].id, async: true, success: function(data) {
                // 2. Delete device inputs
                $.ajax({ url: path+"input/list.json", async: true, success: function(data) {
                    // get list of device inputs
                    var device_inputs = [];
                    for (var z in data) {
                       if (data[z].nodeid==schedule.device) {
                          device_inputs.push(1*data[z].id);
                       }
                    }
                    console.log("Deleting inputs:");
                    console.log(device_inputs);
                    $.ajax({ url: path+"input/delete.json", data: "inputids="+JSON.stringify(device_inputs), async: true, success: function(data){
                        location.href = "/";
                    }});
                }});
            }});
        });

        function calc_schedule() {
            $("#mode button[mode="+schedule.ctrlmode+"]").addClass('active').siblings().removeClass('active');
            if (schedule.ctrlmode=="timer") { $(".smart").hide(); $(".timer").show(); $(".repeat").show(); }
            if (schedule.ctrlmode=="smart") { $(".smart").show(); $(".timer").hide(); $(".repeat").show(); }
            if (schedule.ctrlmode=="on") { $(".smart").hide(); $(".timer").hide(); $(".repeat").hide(); }
            if (schedule.ctrlmode=="off") { $(".smart").hide(); $(".timer").hide(); $(".repeat").hide(); }
            
            $("#period input[type=time]").val(timestr(schedule.period,false));
            $("#end input[type=time]").val(timestr(schedule.end,false));

            $("#timer_start1 input[type=time]").val(timestr(schedule.timer_start1,false));
            $("#timer_stop1 input[type=time]").val(timestr(schedule.timer_stop1,false));
            $("#timer_start2 input[type=time]").val(timestr(schedule.timer_start2,false));
            $("#timer_stop2 input[type=time]").val(timestr(schedule.timer_stop2,false));

            if (schedule.flowT!=undefined) {
                $("#flowT input[type=text]").val(schedule.flowT.toFixed(1)+"C");
            }
            
            for (var i=0; i<7; i++) {
                $(".weekly-scheduler[day="+i+"]").attr("val",schedule.repeat[i]);
            }
            
            $(".scheduler-checkbox[name='interruptible']").attr("state",schedule.interruptible);
            
            $(".scheduler-select[name='signal']").val(schedule.signal);
            
            submit_schedule(0);
            last_submit = (new Date()).getTime();
            setTimeout(function(){
                if (((new Date()).getTime()-last_submit)>1900) {
                   console.log("save");
                   submit_schedule(1);
                }
            },2000);
        }

        function submit_schedule(save) {

            $.ajax({ url: emoncmspath+"demandshaper/submit?schedule="+JSON.stringify(schedule)+"&save="+save+apikeystr,
                dataType: 'json',
                async: true,
                success: function(result) {
                    schedule = (result==null || result.schedule==null) ? {} : result.schedule;
                    success = !(result.hasOwnProperty('success') && result.success === false);
                    if (success){
                        draw_schedule_output(schedule);
                    }
                }
            });
        }

        function draw_schedule_output(schedule)
        {    
            // --------------------------------------------------------------------------------------------
            // Schedule summaries
            // --------------------------------------------------------------------------------------------
            if (schedule.periods && schedule.periods.length) {
                var now = new Date();
                var now_hours = (now.getHours() + (now.getMinutes()/60));
                var period_start = (schedule.periods[0].start[1]);
                
                var startsin = 0;
                if (now_hours>period_start) {
                   startsin = (24 - now_hours) + period_start
                } else {
                   startsin = period_start - now_hours
                }
                
                var hour = Math.floor(startsin);
                var mins = Math.round(60*(startsin-hour));
                var text = "Starts in "+mins+" mins";
                if (hour>0) text = "Starts in "+hour+" hrs "+mins+" mins";
                if (hour>=23 && mins>=30) text = "On";
                //if (controls["active"].value==0) text = "Off"; 
                $(".startsin").html(text);
            }
            
            // Output schedule periods
            var periods = [];
            for (var z in schedule.periods) {
                periods.push(timestr(1*schedule.periods[z].start[1],true)+" to "+timestr(1*schedule.periods[z].end[1],true));
            }
            var out = "<br>"; 
            if (periods.length) {
                out = jsUcfirst(device)+" scheduled to run: <b>"+periods.join(", ")+"</b><br>";
            }
            $("#schedule-output").html(out);
                
            $("#timeleft").html(Math.round(schedule.timeleft/60)+" mins left to run");

            // --------------------------------------------------------------------------------------------
            // Draw schedule graph
            // --------------------------------------------------------------------------------------------
            if (schedule.probability!=undefined) {
                var probability = schedule.probability;
                
                var interval = 1800;
                interval = (probability[1][0]-probability[0][0])*0.001;
                
                var hh = 0;
                for (var z in probability) {
                    if (1*probability[z][2]==schedule.end) hh = z;
                }

                // Shade out time after end of schedule
                var markings = [];
                if (hh>0 && periods.length) markings.push({ color: "rgba(0,0,0,0.1)", xaxis: { from: probability[hh][0] } });
                options.grid.markings = markings;
                
                // Show bars if interval is 1800s
                var bars = true;
                //if (interval<1800) bars = false;
                
                if (bars) {
                    options.bars = { show: true, barWidth:interval*1000*0.8, lineWidth:0 };
                } else {
                    options.lines = {fill:true};
                }
                
                // Generate series for active and inactive slots
                var sum = 0;
                var sum_n = 0;
                var peak = 0;
                available = [];
                unavailable = [];
                for (var z in probability) {
                    var time = probability[z][0];
                    var value = probability[z][1];
                    var active = probability[z][4];
                    if (value>peak) peak = value;
                        
                    if (active) { 
                        available.push([time,value]); 
                        sum += value; sum_n++;
                        if (bars) value=null;
                    } 
                    unavailable.push([time,value]);
                }
                
                // Display CO2 in window
                var out = "";
                if (sum_n>0) {
                    var mean = sum/sum_n;
                    
                    if (schedule.signal=="carbonintensity") {
                        var co2_km = (mean / 4.0) / 1.6;
                        var prc = 100-(100*(co2_km / 130));
                        if (devices[device].type=="openevse") out = "Charge ";
                        out += "CO2 intensity: "+Math.round(mean)+" gCO2/kWh"
                        if (devices[device].type=="openevse") {
                            out += ", "+Math.round(co2_km)+" gCO2/km, "+Math.round(prc)+"% <span title='Compared to 50 MPG Petrol car'>reduction</span>.";
                        } else if (devices[device].type=="hpmon") {
                            out += ", "+Math.round(mean/3.8)+" gCO2/kWh Heat @ COP 3.8";
                        } else {
                            out += ", "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak";
                        }
                    } else if (schedule.signal=="octopus" || schedule.signal=="economy7") {

                        if (devices[device].type=="openevse") {
                            var p_per_mile = (mean / 4.0);
                            var prc = 100-(100*(p_per_mile / 10.0));
                            out = "Cost: "+(p_per_mile).toFixed(1)+"p/mile"
                            
                        } else if (devices[device].type=="hpmon") {
                            out = ", "+Math.round(mean/3.8)+" p/kWh Heat @ COP 3.8";
                        } else {
                            out = "Average cost: "+mean.toFixed(1)+"p/kWh, "+Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak";
                        }  
                    } else {
                        out = Math.round(100.0*(1.0-(mean/peak)))+"% reduction vs peak";  
                    }
                }
                $("#schedule-co2").html(out);
                
                draw_graph();
            }
        }

        function draw_graph() {
            var width = $("#placeholder_bound").width();
            if (width>0) {
                $("#placeholder").width(width);
                $.plot($('#placeholder'), [{data:available,color:"#ea510e",fill:1.0},{data:unavailable,color:"#888"}], options);
            }
        }

        // -------------------------------------------------------------------------
        // Misc functions
        // -------------------------------------------------------------------------
        function jsUcfirst(string) {return string.charAt(0).toUpperCase() + string.slice(1);}

        function t(){};

        function timestr(hour,type){
            
            var h = Math.floor(hour);
            var m = (hour - h) * 60;
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

        $(window).resize(function(){
            draw_graph();
        });
        
    } else {
        $("#scheduler-outer").hide();
        $("#no-devices-found").show();
    }
}
