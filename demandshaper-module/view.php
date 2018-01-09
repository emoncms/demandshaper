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

#table {
    margin: 0 auto;
    width:960px;
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
    color:#ea510e;
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

</style>

<div style="height:20px"></div>

<div id="table">
  <div class="node-scheduler-title"><?php echo $device; ?></div>
  <div class="node-scheduler" node="<?php echo $device; ?>"></div>
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


