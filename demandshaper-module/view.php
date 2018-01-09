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
    <button id="clear" class="btn">Clear</button>
    <br><br>
    <p><b>Schedule Output:</b><div id="schedule-output"></div></p>
    
    <div id="placeholder_bound" style="width:100%; height:300px;">
      <div id="placeholder" style="height:300px"></div>
    </div>
    
    <p>Higher bar height equalls more power available</p>
    
</div>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/scheduler.js"></script>

<script>
var emoncmspath = "<?php echo $path; ?>";
var device = "<?php echo $device; ?>";
var devices = {};

$.ajax({ url: emoncmspath+"device/list.json", dataType: 'json', async: false, success: function(result) { 
    // Associative array of devices by nodeid
    devices = {};
    for (var z in result) devices[result[z].nodeid] = result[z];
}});

draw_scheduler(device);

</script>


