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

$device = "";
if (isset($_GET['node'])) $device = $_GET['node'];

$v=5;

?>

<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.selection.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.touch.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/vis/visualisations/common/vis.helper.js"></script>

<style>

#scheduler-outer {
    margin: 0 auto;
    max-width:960px;
    font-size:16px;
}

.node-scheduler {
    padding: 5px 5px 5px 5px;
    background-color: #ea510e;
    text-align:left;
}

.node-scheduler-title {
    padding: 10px;
    background-color: #ea510e;
    color:#fff;
    font-weight:bold;
}

.scheduler-inner {
    background-color:#fff;
    padding:10px;
    color:#ea510e;
    font-weight:bold;
}

.scheduler-inner2 {
    background-color:#f0f0f0;
    padding:20px;
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
    font-size:16px;
}

.weekly-scheduler-day[val="1"] {
    background-image:url("<?php echo $path; ?>Modules/demandshaper/day-enabled.png");
}

.scheduler-checkbox {
    display:inline-block;
    width:50px;
    height:31px;
    background-image:url("<?php echo $path; ?>Modules/demandshaper/checkbox_inactive.png");
    background-size:50px;
    cursor:pointer;
    float:left;
}

.scheduler-checkbox-label {
  padding-top:7px;
  padding-left:10px;
  float:left;
}

.scheduler-checkbox[state="1"] {
    background-image:url("<?php echo $path; ?>Modules/demandshaper/checkbox_active.png");
}

.saved { color:#888 }

.scheduler-title {
    padding-top:5px;
    padding-bottom:10px;
}

.scheduler-startsin {
    padding:3px;
    padding-left:10px;
    padding-right:10px;
    background-color:#f29200;
    float:right;
    color:#fff;
    border-radius: 10px;
    font-size:14px;
}

.schedule-output-heading {
    background-color:#f29200;
    color:#fff;
    padding:10px;
    cursor:pointer;
}

.schedule-output-box {
    background-color:#fff;
    padding:10px;
    font-weight:normal;
}

.triangle-dropdown {
    margin-top:5px;
    float:right;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 10px solid #fff;
}

.triangle-pushup {
    margin-top:5px;
    float:right;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 10px solid #fff;
}

body {padding:0}

.container-fluid { padding:0 }

#footer {
    margin-left: 0px;
    margin-right: 0px;
}

.navbar-fixed-top {
    margin-left: 0px;
    margin-right: 0px;
}

.weekly-scheduler-text {
    margin-top:15px;
}

.icon-smartplug {
  display: inline-block;
  width: 18px;
  height: 18px;
  background-image: url('<?php echo $path; ?>Modules/demandshaper/smartplug.png');
  background-position: 0 0;
  background-size:18px;
  background-repeat: no-repeat;
  margin-right:10px;
}

.icon-openevse {
  display: inline-block;
  width: 20px;
  height: 20px;
  background-image: url('<?php echo $path; ?>Modules/demandshaper/openevse.png');
  background-position: 0 0;
  background-size:20px;
  background-repeat: no-repeat;
  margin-right:10px;
}
  
@media (max-width: 767px) {
  
  #scheduler-outer {
    margin-left:5px;
    margin-right:5px;
  }
  
  #scheduler-top {
    height:5px;
  }
  
  .scheduler-inner {
    padding:10px;
    background-color:#f0f0f0;
  }
  
  .scheduler-inner2 {
    padding:0px;
    background-color:none;
  }
  
  .weekly-scheduler-day {
    margin-right:0px;
    width:42px; 
    height:42px; 
    background-size:42px;
    font-size:12px;
  }
  
  .weekly-scheduler-text {
      margin-top:12px;
  }
}

@media (min-width: 767px) {
  
  #scheduler-outer {
    margin-left:10px;
    margin-right:10px;
  }
  
  #scheduler-top {
    height:10px;
  }
}


</style>
<link rel="stylesheet" href="<?php echo $path; ?>Lib/misc/sidebar.css?v=<?php echo $v; ?>">

<div id="wrapper">
  <div class="sidenav">
    <div class="sidenav-inner">
      <ul class="sidenav-menu"></ul>
    </div>
  </div>
  
  <div id="scheduler-top"></div>

  <div id="scheduler-outer">
    <div class="node-scheduler-title"></div>
    <div class="node-scheduler" node="">

      <div class="scheduler-inner">
        <div class="scheduler-startsin"><span class='startsin'></span></div>
        <div class="scheduler-title">Schedule</div>

        <div class="scheduler-inner2">
          <div class="scheduler-controls">
          
            <!---------------------------------------------------------------------------------------------------------------------------->
            <!-- CONTROLS -->
            <!---------------------------------------------------------------------------------------------------------------------------->
            <div name="active" state=0 class="input scheduler-checkbox"></div>
              <div class="scheduler-checkbox-label">Active</div>
              <div style='clear:both'></div>
            <br>
            
            <div style="display:inline-block; width:120px;">Run period:</div>
              <input class="input timepicker-hour" type="text" name="period-hour" style="width:45px" /> hrs
              <input class="input timepicker-minute" type="text" name="period-minute" style="width:45px" /> mins
            <br>

            <div style="display:inline-block; width:120px;">Complete by:</div>
              <input class="input timepicker-hour" type="text" name="end-hour" style="width:45px" /> : 
              <input class="input timepicker-minute" type="text" name="end-minute" style="width:45px" />
            <br>
            <br>
            <div name="interruptible" state=0 class="input scheduler-checkbox"></div>
              <div class="scheduler-checkbox-label">Ok to interrupt schedule</div>
              <div style='clear:both'></div>
            <br>
            
            <p>Repeat:</p>
            <div class="weekly-scheduler-days">
              <div name="repeat" day=0 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Mon</div></div>
              <div name="repeat" day=1 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Tue</div></div>
              <div name="repeat" day=2 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Wed</div></div>
              <div name="repeat" day=3 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Thu</div></div>
              <div name="repeat" day=4 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Fri</div></div>
              <div name="repeat" day=5 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Sat</div></div>
              <div name="repeat" day=6 val=0 class="input weekly-scheduler weekly-scheduler-day"><div class="weekly-scheduler-text">Sun</div></div>
            </div>
            <br>
            <!---------------------------------------------------------------------------------------------------------------------------->
          </div>

          <button class="scheduler-save btn">Save</button><button class="scheduler-clear btn" style="margin-left:10px">Clear</button>
          <br><br>
          <div class="schedule-output-heading"><div class="triangle-dropdown hide"></div><div class="triangle-pushup"></div>Schedule Output</div>

          <div class="schedule-output-box">
            <div id="schedule-output"></div>
            <div id="placeholder_bound" style="width:100%; height:300px">
              <div id="placeholder" style="height:300px"></div>
            </div>

            Higher bar height equalls more power available
          </div> <!-- schedule-output-box -->
          <br>
          <span class="">Demand shaper signal: </span>
          <select name="signal" class="input scheduler-select" style="margin-top:10px">
              <option value="carbonintensity">UK Grid Carbon Intensity</option>
              <option value="octopus">Octopus Agile (D)</option>
              <option value="cydynni">Energy Local: Bethesda</option>
              <option value="economy7">Economy 7</option>
          </select>
        </div> <!-- schedule-inner2 -->
      </div> <!-- schedule-inner -->
    </div> <!-- node-scheduler -->
  </div> <!-- table -->
</div>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/scheduler.js?v=<?php echo $v; ?>"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/misc/sidebar.js?v=<?php echo $v; ?>"></script>

<script>
init_sidebar({menu_element:"#demandshaper_menu"});

var emoncmspath = "<?php echo $path; ?>";
var device = "<?php echo $device; ?>";
var devices = {};

$.ajax({ url: emoncmspath+"device/list.json", dataType: 'json', async: false, success: function(result) { 
    // Associative array of devices by nodeid
    devices = {};
    var out = "";
    for (var z in result) {
        if (result[z].type=="openevse" || result[z].type=="smartplug") {
            devices[result[z].nodeid] = result[z];
            // sidebar list
            out += "<li><a href='"+emoncmspath+"demandshaper?node="+result[z].nodeid+"'><span class='icon-"+result[z].type+"'></span>"+ucfirst(result[z].nodeid)+"</a></li>";
            // select first device if device is not defined
            if (device=="") device = result[z].nodeid;
        }
    }
    
    $(".sidenav-menu").html(out);
}});

draw_scheduler(device);

function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
</script>


