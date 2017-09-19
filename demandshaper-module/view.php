<?php 
/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

global $path;
$device = $_GET['node'];

?>

<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.selection.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.touch.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/vis/visualisations/common/vis.helper.js"></script>

<style>

.box {
    padding:20px;
    background-color:#f6f6f6;
    border: 1px solid #ddd;
    margin: 0 auto;
    max-width:700px;
}

.weekly-scheduler-day {
    display:inline-block;
    margin-right:5px;
    width:50px; 
    height:50px; 
    background-image:url("<?php echo $path; ?>Modules/demandshaper/day.png");
    background-size:50px;
    color:#fff;
    font-weight:bold;
    text-align:center;
    cursor:pointer;
}

.weekly-scheduler-day[val="1"] {
    background-image:url("<?php echo $path; ?>Modules/demandshaper/day-enabled.png");
}

.saved { color:#888 };

</style>

<div style="height:20px"></div>

<div class="box">
    <h2 id="devicename"></h2><hr>
    <div id="controls"></div>

    <button id="save" class="btn">Save</button>
    <br><br>
    <p><b>Schedule Output:</b><div id="schedule-output"></div></p>
    
    <div id="placeholder_bound" style="width:100%; height:300px;">
      <div id="placeholder" style="height:300px"></div>
    </div>
    
    <p>Higher bar height equalls more power available</p>
    
</div>

<script>

var path = "<?php echo $path; ?>";
var device = "<?php echo $device; ?>";
$("#devicename").html(jsUcfirst(device));

var controls = {};
var previousPoint = false;
var available = [];
var unavailable = [];
var options = {};
var device_type = false;


$.ajax({ url: path+"device/list.json", dataType: 'json', async: false, success: function(devicelist) { 
   for (var z in devicelist) {
       if (devicelist[z].name==device) device_type = devicelist[z].type;
   }
}});


$.ajax({ url: path+"device/template/get.json?device="+device_type, dataType: 'json', async: true, success: function(template) { 
    controls = template.control;
    
    $.ajax({ url: path+"demandshaper/get?device="+device, dataType: 'json', async: false, success: function(result) {

        for (var property in controls) {
            if (result!=null && result.schedule!=null && result.schedule[property]!=undefined) {
                controls[property].value = result.schedule[property];
            } else {
                controls[property].value = controls[property].default;
            }
        }
        
        draw_schedule_output(result);
    }});
    
    draw_controls();
    update();
}});

// -------------------------------------------------------------------------


$("#save").click(function(){

    var tosave = {};
    for (var property in controls) {
        tosave[property] = controls[property].default;
    }
    
    for (var property in controls) {
        if (controls[property].type=="text") 
            tosave[property] = $("input[name='"+property+"']").val();
        if (controls[property].type=="checkbox") 
            tosave[property] = 1*$("input[name='"+property+"']")[0].checked;
        if (controls[property].type=="time")
            tosave[property] = (1*$("input[name='"+property+"-hour']").val()) + ($("input[name='"+property+"-minute']").val()/60);
        if (controls[property].type=="weekly-scheduler") {
            tosave[property] = [];
            for (var i=0; i<7; i++) {
                tosave[property][i] = $(".weekly-scheduler[name='"+property+"'][day="+i+"]").attr("val")*1;
                if (tosave[property][i]) tosave.runonce = false;
            }
        }
    }
    
    save(tosave);
});

// -------------------------------------------------------------------------

function draw_controls() {
    var out = "";
    for (var property in controls) {
        
        if (controls[property].type=="text")
            out += "<p>"+jsUcfirst(property)+":<br><input type='text' name='"+property+"' value='"+controls[property].value+"' /></p>";
        if (controls[property].type=="checkbox") {
            var checked = "";
            if (controls[property].value) checked = "checked";
            out += "<p>"+jsUcfirst(property)+": <input type='checkbox' name='"+property+"' "+checked+" /></p>";
        }

        // ----------------------------------------------------------------------------------------------------
        // Draw time picker
        // ----------------------------------------------------------------------------------------------------  
        if (controls[property].type=="time") {
            // Use name property if present, otherwise use key
            var name = jsUcfirst(property);
            if (controls[property].name!=undefined) name = controls[property].name;
            // Draw time picker
            
            var time = controls[property].value;
            var hour = Math.floor(time);
            var mins = 60*(time-hour);
            if (hour<10) hour = "0"+hour;
            if (mins<10) mins = "0"+mins;
            
            out += '<p><div style="display:inline-block; width:90px;">'+name+":</div> ";
            out += '  <input class="timepicker-hour" type="text" name="'+property+'-hour" style="width:30px" value='+hour+' />:';
            out += '  <input class="timepicker-minute" type="text" name="'+property+'-minute" style="width:30px" value='+mins+' />';
            out += '</p>';
            
        }
        
        // ----------------------------------------------------------------------------------------------------
        // Draw weekly scheduler
        // ----------------------------------------------------------------------------------------------------
        if (controls[property].type=="weekly-scheduler") {
            var days = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
            out += '<div>';
            out += '<p>Repeat:</p>';
            out += '<div class="weekly-scheduler-days">';
            for (var i=0; i<7; i++) {
                var selected = controls[property].value[i];
                out += '<div name="'+property+'" day='+i+' val='+selected+' class="weekly-scheduler weekly-scheduler-day"><div style="padding-top:15px">'+days[i]+'</div></div>';
            }
            out += '</div>';
            out += '</div><br>';
        }
            
    }
    $("#controls").html(out);
   
}

function update() {
    $.ajax({ url: path+"input/get/"+device, dataType: 'json', async: true, success: function(data) {
        inputs = data;
        for (var property in controls) {
            if (controls[property].type=="text" && inputs[property]!=undefined) 
                $("input[name='"+property+"']").val(inputs[property].value);
            if (controls[property].type=="checkbox" && inputs[property]!=undefined) 
                $("input[name='"+property+"']")[0].checked = inputs[property].value;
        }
    }});
}

function save(data) {

    // ----------------------------------------------------------------------------------------------------
    // Publish control parameters to emoncms inputs & publish to MQTT
    // ----------------------------------------------------------------------------------------------------
    var mqttpub = {}; 
    var count = 0;
    for (var property in data) {
        if (data[property].mqttpub!=undefined && data[property].mqttpub) {
            mqttpub[property] = data[property];
            count++;
        }
    }
    if (count) {
        $.ajax({ url: path+"input/post/"+device+"?data="+JSON.stringify(mqttpub)+"&mqttpub=1", dataType: 'text', async: true, success: function(result) {
             if (result=="ok") $(".saved").show();
        }});
    }
    
    // ----------------------------------------------------------------------------------------------------
    // Scheduler
    // ----------------------------------------------------------------------------------------------------
    var schedule = data;
    schedule.device = device;
    schedule.basic = 0;
    
    console.log(schedule);

    $.ajax({ url: path+"demandshaper/submit?schedule="+JSON.stringify(schedule), dataType: 'json', async: true, success: function(result) {
        draw_schedule_output(result);
    }});
}

function draw_schedule_output(result)
{
    var schedule = result.schedule;
    if (result==null || result.schedule==null) return false;

    var out = jsUcfirst(device)+" scheduled to run: ";

    var periods = [];
    for (var z in schedule.periods) {

        var start = 1*schedule.periods[z].start;
        if (start==0) start = "Midnight";
        else if (start==12) start = "Noon";
        else if (start>12) {
            start = (start - 12)+"pm";
        } else if (start<12) {
            start = start+"am";
        }
        
        var end = 1*schedule.periods[z].end;
        if (end==0) end = "Midnight";
        if (end==12) end = "Noon";
        else if (end>12) {
            end = (end - 12)+"pm";
        } else if (end<12) {
            end = end+"am";
        }
        periods.push(start+" to "+end+" ");
    }

    out += "<b>"+periods.join(", ")+"</b>";

    //out += JSON.stringify(schedule);

    $("#schedule-output").html(out);

    if (schedule.probability!=undefined) {
        var probability = schedule.probability;

        var hh = 0;
        for (var z in probability) {
            if (1*probability[z][2]==schedule.end) hh = z;
        }

        var markings = [];
        // { color: "#000", lineWidth: 2, xaxis: { from: probability[hh][0], to: probability[hh][0] } },
        if (hh>0) markings.push({ color: "rgba(0,0,0,0.1)", xaxis: { from: probability[hh][0] } });


        options = {
            bars: { show: true, barWidth:1800*1000*0.75 },// align: 'center'
            xaxis: { mode: "time", timezone: "browser" },
            yaxis: { min: 0 },
            grid: {hoverable: true, clickable: true, markings: markings},
            selection: { mode: "x" },
            touch: { pan: "x", scale: "x" }
        }

        available = [];
        unavailable = [];
        for (var z in probability) {
            if (probability[z][4]) available.push([probability[z][0],probability[z][1]]);
            if (!probability[z][4]) unavailable.push([probability[z][0],probability[z][1]]);
        }

        $.plot($('#placeholder'), [{data:available,color:"#ff0000"},{data:unavailable,color:"#888"}], options);
    }

}

function resize()
{
    var width = $("#placeholder_bound").width();
    $("#placeholder").width(width);
    //$('#household_bargraph_placeholder_bound').height(h);
    //$('#household_bargraph_placeholder').height(h);
    $.plot($('#placeholder'), [{data:available,color:"#ff0000"},{data:unavailable,color:"#888"}], options);
}

$("#controls").on("change",".timepicker-minute",function(){
    var val = $(this).val();
    val = Math.floor(val/30)*30;
    if (val<0) val = 0;
    if (val>59) val = 30;
    if (val<10) val = "0"+val;
    $(this).val(val);
});

$("#controls").on("change",".timepicker-hour",function(){
    var val = $(this).val();
    val = Math.round(val);
    if (val<0) val = 0;
    if (val>23) val = 23;
    if (val<10) val = "0"+val;
    $(this).val(val);
});

$("#controls").on("click",".weekly-scheduler-day",function(){
    var val = $(this).attr('val');
    if (val==0) {
        $(this).attr('val',1);
    } else {
        $(this).attr('val',0);
    }
});

$("#controls").on("click",".weekly-scheduler-repeat",function(){
    if ($(this)[0].checked) {

    } else {
    
    }
});

$('#placeholder').bind("plothover", function (event, pos, item)
{
    if (item) {
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;

            $("#tooltip").remove();
            var itemTime = item.datapoint[0];
            var itemVal = item.datapoint[1];
            var datestr = (new Date(itemTime)).format("HH:MM ddd");//, mmm dS");
            tooltip(item.pageX, item.pageY, datestr+"<br>val:"+itemVal.toFixed(1), "#DDDDDD");
        }
    }
    else
    {
        $("#tooltip").remove();
        previousPoint = null;
    }
});

$(window).resize(function(){
    resize();
});

function jsUcfirst(string) {return string.charAt(0).toUpperCase() + string.slice(1);}
</script>
