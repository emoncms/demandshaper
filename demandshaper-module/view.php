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
if (isset($_GET['device'])) $device = $_GET['device'];

$v=11;

$emoncmspath = $path;
if ($remoteaccess) $emoncmspath .= "remoteaccess/";

?>

<script>
var path = "<?php echo $path; ?>";
var emoncmspath = "<?php echo $emoncmspath; ?>";
var device_name = "<?php echo $device; ?>";
var devices = {};

var apikeystr = "";
if (window.session!=undefined) {
    apikeystr = "&apikey="+session["apikey_write"];
}
</script>
<style>
    #icon-list svg {
        opacity: .7
    }
}
</style>
<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.selection.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.touch.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/vis/visualisations/common/vis.helper.js"></script>

<link rel="stylesheet" href="<?php echo $path; ?>Modules/demandshaper/demandshaper.css?v=<?php echo $v; ?>">
<script type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/battery.js?v=<?php echo $v; ?>"></script>

  <div id="scheduler-top"></div>
  
  <div id="auth-check" class="hide">
      <i class="icon-exclamation-sign icon-white"></i> Device on ip address: <span id="auth-check-ip"></span> would like to connect 
      <button class="btn btn-small auth-check-btn auth-check-allow">Allow</button>
  </div>

  <div id="no-devices-found" class="hide">

      <h2 id="no-devices-found-title">No Devices Found</h2>
      
      <h2 id="icon-list">
        <svg class="icon plus"><use xlink:href="#icon-smartplug"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-hpmon"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-openevse"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-emonth"></use></svg>
      </h2>
      <div style="height:10px"></div>

      <div id="no-devices-found-checking">
          <p>Checking for pairing request</p><br>
          <img src="<?php echo $path; ?>Modules/demandshaper/ajax-loader.gif">
          <br><br>
          
          <div class="wizard-box-controls">
            <div class="wizard-title">Smartplug Setup <span class="p-2" style="font-size:larger"></span></div>
            <div class="wizard-next">Next</div>
            <div class="wizard-back">Back</div>
          </div>
          <div class="wizard-box" step=0></div>
          
      </div>
  </div>

  <?php
      if (strpos($device,"emonth")!==false) {
          include "Modules/demandshaper/emonth.php";
      } else {
          include "Modules/demandshaper/general.php";
      }
  ?>

  <script>

  device_id = false
  device_type = false
  device_loaded = false
  
  update_sidebar();
  setInterval(update_sidebar,10000);
  function update_sidebar() {
      $.ajax({ url: emoncmspath+"demandshaper/list", dataType: 'json', async: true, success: function(result) {
          devices = result;
          
          // Build menu
          // var out = "";
          for (var name in devices) {
          //  out += "<li><a href='"+path+"demandshaper?node="+name+"'><span class='icon-"+devices[name].type+"'></span>"+ucfirst(name)+"</a></li>";
          //  select first device if device is not defined
              if (!device_name) device_name = name;
          }
          // out += "<li id='add-device' style='border-top:1px solid #aaa; cursor:pointer'><a><i class='icon-plus icon-white'></i> Add Device</a></li>";
          // $(".sidenav-menu").html(out);
          
          if (!device_loaded) {
              if (device_name && devices[device_name]!=undefined) {
                  hide_device_finder();
                  
                  device_id = devices[device_name].id;
                  device_type = devices[device_name].type;
                  
                  load_device(device_id, device_name, device_type);
              } else {
                  show_device_finder();
              }
          }
      }});
  }

  function ucfirst(string) {
      return string.charAt(0).toUpperCase() + string.slice(1);
  }
  </script>

<script>
var emoncmspath = "<?php echo $emoncmspath; ?>";
// -------------------------------------------------------------------------------------------------------
// Device authentication transfer
// -------------------------------------------------------------------------------------------------------
var auth_check_interval = false;
function auth_check(){
    $.ajax({ url: emoncmspath+"device/authcheck.json", dataType: 'json', async: true, success: function(data) {
        if (typeof data.ip !== "undefined") {
            $("#auth-check-ip").html(data.ip);
            $("#auth-check").show();
            $("#table").css("margin-top","0");
            $("#no-devices-found-title").html("Device Found");
            $("#no-devices-found-checking").html("Click Allow to pair device");
        } else {
            $("#table").css("margin-top","3rem");
            $("#auth-check").hide();
        }
    }});
}

$(".auth-check-allow").click(function(){
    var ip = $("#auth-check-ip").html();
    $.ajax({ url: emoncmspath+"device/authallow.json?ip="+ip, dataType: 'json', async: true, success: function(data) {
        $("#auth-check").hide();
        $("#no-devices-found-checking").html("Please wait for device to connect");
    }});
});

$(".sidenav-menu").on("click","#add-device",function(){
    show_device_finder();
});

function show_device_finder() {
    $("#no-devices-found").show();
    $("#scheduler-outer").hide();
    auth_check();
    clearInterval(auth_check_interval);
    auth_check_interval = setInterval(auth_check,5000);
}

function hide_device_finder() {
    $("#no-devices-found").hide();
    clearInterval(auth_check_interval);
}

var step = 0;
var steps = [

    "Plug your smart plug into an electrical socket. The light on the plug will show green for 3 seconds followed by a short off period and then a couple of very short flashes. This indicates that the plug is working and has created a WIFI Access Point.",
    
    "The WIFI Access Point should appear in your laptop or phones available WIFI networks, the SSID will contain the name smartplug followed by a number e.g: 'smartplug1'.<br><br>Connect to this network, once connected click on the following link to open the smartplug configuration interface in a new window: <b>http://192.168.4.1</b>",
    
    "On the smartplug configuration interface select the WIFI network you wish to connect to, enter the passkey and click connect.<br><br>The green light on the smartplug will now turn on again. If the connection is successful you will see 10 very fast consecutive flashes.<br><br>The web interface will also show that the module has connected and its IP address.",
    
    "<b>Failed Connection</b><br>If the smartplug fails to connect to the selected WIFI network the green LED will stay on with a slight pulsing rythym for 30 seconds before the plug automatically resets and tries again. To re-enter setup mode hold the button on the front of the smartplug down while the green LED is on.",
    
    "With the smartplug WIFI settings configured connect back to you home network and keep this window open.<br><br>After a couple of minutes a notice will appear asking whether to allow device at the given ip address to connect.<br><br>Click allow and wait a couple of minutes for the device to appear."
]

$(".wizard-box").html(steps[step]);
if (step==0) $(".wizard-back").hide();

indicator = function(_step) {
    let off = '○',
        on = '●',
        indicator = '';
    steps.forEach(function(item,index) {
        indicator += index < _step + 1 ? on: off;
    })
    return indicator
}
$(".wizard-title span").text(indicator(0));

$(".wizard-next").click(function(){
    end = steps.length-1
    step++;
    if (step>end) step=end;
    $(".wizard-box").html(steps[step]);
    $(".wizard-back").show();
    if (step==end) $(".wizard-next").hide();
    $(".wizard-title span").text(indicator(step));
});

$(".wizard-back").click(function(){
    step--;
    if (step<0) step=0;
    $(".wizard-box").html(steps[step]);
    $(".wizard-next").show();
    if (step==0) $(".wizard-back").hide();
    $(".wizard-title span").html(indicator(step));
});

</script>
