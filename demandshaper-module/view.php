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

$v=2;

$emoncmspath = $path;
if ($remoteaccess) $emoncmspath .= "remoteaccess/";

?>

<script>
var emoncmspath = "<?php echo $emoncmspath; ?>";
var device_name = "<?php echo $device; ?>";
var devices = {};

var apikeystr = "&apikey=<?php echo $apikey; ?>";

var forecast_list = <?php echo json_encode($forecast_list); ?>;

</script>
<style>
    #icon-list svg {
        opacity: .7
    }
}
</style>
<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
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

  <div id="wizard" class="hide">
      
      <h2>Demand Shaper Module</h2>
      <p>Schedule your smart devices to run at the best time using cost, carbon<br>and local renewable power availability forecasts for the day ahead.</p>
      
      <h2 id="icon-list">
        <svg class="icon plus"><use xlink:href="#icon-smartplug"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-hpmon"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-openevse"></use></svg>
        <svg class="icon plus"><use xlink:href="#icon-emonth"></use></svg>
      </h2>
      <div style="height:10px"></div>

      <div style="border-bottom:1px solid #fff">
      <div class="wizard-option-l1" name="add-device"><svg class="icon"><use xlink:href="#icon-plus"></use></svg> Add Device</div>
      <div class="wizard-group hide" name="add-device">
          <div class="wizard-option-l2" name="smartplug"><svg class="icon"><use xlink:href="#icon-smartplug"></use></svg> SonOff Smart Plug</div>
          <div class="wizard-option-l3 hide" name="smartplug">
          <p>1. Plug your smart plug into an electrical socket. The light on the plug will show green for 3 seconds followed by a short off period and then a couple of very short flashes. This indicates that the plug is working and has created a WIFI Access Point.</p>
          
          <p>2. The WIFI Access Point will then appear in your laptop or phones available WIFI networks, the SSID will contain the name smartplug followed by a number e.g: 'smartplug1'. Connect to this network. Once connected click on the following link to open the smartplug configuration interface in a new window: <b><a href="http://192.168.4.1" target="_blank" style="color:#fff">http://192.168.4.1</a></b></p>
    
          <p>3. On the smartplug configuration interface select your home WIFI network, enter the passkey and click connect. The green light on the smartplug will now turn on again. If the connection is successful you will see 10 very fast consecutive flashes. The web interface will also show that the module has connected and its IP address.</p>
    
          <p><b>Failed Connection</b><br>If the smartplug fails to connect to the selected WIFI network the green LED will stay on with a slight pulsing rythym for 30 seconds before the plug automatically resets and tries again. To re-enter setup mode hold the button on the front of the smartplug down while the green LED is on.</p>
    
          <p>4. With the smartplug WIFI settings configured, connect back to you home network and keep this window open. After a couple of minutes a notice will appear asking whether to allow device at the given ip address to connect. Click allow and wait a couple of minutes for the smart plug to appear in the left hand menu. Click on the smart plug to start scheduling it.</p>
          </div>
          <div class="wizard-option-l2" name="emonevse"><svg class="icon"><use xlink:href="#icon-openevse"></use></svg> OpenEVSE Charging Station</div>
          <div class="wizard-option-l3 hide" name="emonevse">
          
          <p>See <b><a href="https://guide.openenergymonitor.org/integrations/evse-setup/" target="_blank" style="color:#fff">https://guide.openenergymonitor.org/integrations/evse-setup/</a></b> for main OpenEVSE setup guide.</p>
          
          <p>1. Once powered up the OpenEVSE will create a Wifi Access Point. Keeping this window open, add a new browser tab and then connect to the OpenEVSE WiFi Access Point with SSID OpenEVSE_xxxx and password openevse. You should get directed to a captive portal where you choose to join a local network. If the captive portal does not work, browse to <b><a href="http://192.168.4.1" target="_blank" style="color:#fff" >http://192.168.4.1</a></b></p>
    
          <p>2. Select your local WIFI network, enter the passkey and click connect.</p>
          
          <p>3. On the main OpenEVSE configuration interface select 'Services'. In the MQTT section, enter the IP address of the hub or its hostname e.g <i>emonpi</i> or <i>emonpi.local</i>. The username should be <i>emonpi</i>, password: <i>emonpimqtt2016</i> and base topic: <i>emon/openevse</i>. Click Save to complete.</p>          
          
          <p>4. Navigate to the emoncms inputs page where a set of OpenEVSE inputs will appear including charge current, energy used and charger state. Click on the Cog to the top-right of the inputs to bring up the 'Configure Device' window. Select the EVSE > OpenEVSE > Default device template and then click Initialize and Initialize again to confirm.</p>
          
          <p>5. Navigate back to this page, the OpenEVSE device should now appear in the left hand menu. Click on the OpenEVSE menu item to start scheduling it.</p>
          </div> 
          <div class="wizard-option-l2" name="hpmon"><svg class="icon"><use xlink:href="#icon-hpmon"></use></svg> Heat pump Controller</div>
          <div class="wizard-option-l3 hide" name="hpmon">
          <p><b>Requirements:</b> Heatpump Monitor running EmonESP timer branch, with modification to control Mitsubishi Ecodan heat pump with FTC2 flow temp controller.</p>
          <p>1. Power up the heat pump monitor. The light on the Wifi module will show blue for 3 seconds followed by a short off period and then a couple of very short flashes. This indicates that the module is working and has created a WIFI Access Point.</p>
          
          <p>2. The WIFI Access Point will then appear in your laptop or phones available WIFI networks, the SSID will contain the name hpmon followed by a number e.g: 'hpmon5'. Connect to this network. Once connected click on the following link to open the heat pump monitor configuration interface in a new window: <b><a href="http://192.168.4.1" target="_blank" style="color:#fff">http://192.168.4.1</a></b></p>
    
          <p>3. On the heat pump monitor configuration interface select your home WIFI network, enter the passkey and click connect. The blue light on the heat pump monitor will now turn on again. If the connection is successful you will see 10 very fast consecutive flashes. The web interface will also show that the module has connected and its IP address.</p>
    
          <p><b>Failed Connection</b><br>If the heat pump monitor fails to connect to the selected WIFI network the green LED will stay on with a slight pulsing rythym for 30 seconds before the plug automatically resets and tries again. To re-enter setup mode hold the reset button on the front of the heat pump monitor WiFi module down while the blue LED is on.</p>
    
          <p>4. With the heat pump monitor WIFI settings configured, connect back to you home network and keep this window open. After a couple of minutes a notice will appear asking whether to allow device at the given ip address to connect. Click allow and wait a couple of minutes for the heat pump monitor to appear in the left hand menu. Click on the heat pump monitor to start scheduling it.</p>
          </div>      
          <div class="wizard-option-l2" name="emonth"><svg class="icon"><use xlink:href="#icon-emonth"></use></svg> EmonTH Temperature & Humidity node</div>
          <div class="wizard-option-l3 hide" name="emonth">
          <p>In addition to smart control the Demand Shaper interface is designed to show small pre-built dashboards for different monitoring devices. To see data from an EmonTH Temperature & Humidity node, simply insert batteries to power up and wait a couple of minutes for the EmonTH to appear in the left hand menu.</p>
          </div>       
      </div>
      
      <div class="wizard-option-l1" name="troubleshooting"><svg class="icon"><use xlink:href="#icon-apps"></use></svg>Troubleshooting</div> 
      <div class="wizard-group hide" name="troubleshooting">
          <div class="wizard-option-l2" name="nopairingreq">No pairing request received</div>
          <div class="wizard-option-l3 hide" name="nopairingreq">
          <p>If no pairing request is received, this suggests a WiFi connectivity issue with the smart plug. Switch the smart plug off at the wall, wait 10s and switch it on again. If the smart plug connects successfully you should see 10 very fast consecutive flashes. If after a couple of minutes a pairing request is still not seen try a factory reset of the smart plug as described below.</p>          
          </div>       
          <div class="wizard-option-l2" name="settingsmismatch">Settings mismatch</div>
          <div class="wizard-option-l3 hide" name="settingsmismatch">
          <p>This notice appears if the schedule settings on the device differ to those on the hub, this can happen if the schedule settings did not transfer correctly due a WiFi or other connectivity issue. Try resending the command by clicking on/off or smart again or changing the timing. There is an ongoing issue where this notice sometimes appears when scheduling appliances at 15 minute intervals. Try changing the schedule period to see if the issue goes away.</p>
          </div>       
          <div class="wizard-option-l2" name="unresponsive">Device unresponsive</div>
          <div class="wizard-option-l3 hide" name="unresponsive">
          <p>This notice appears if the hub cant contact the device, it may be switched off at the wall or outside of WiFi range. If it is neither of these try power cycling the device (e.g smart plug) to see if it becomes responsive again. Refresh the page or toggle on/off in the interface to attempt to control the device again.</p>
          </div>
          <div class="wizard-option-l2" name="factoryreset">Smartplug Factory Reset</div>
          <div class="wizard-option-l3 hide" name="factoryreset">
              <p>1. Turn the smart plug off and on at the wall, at the moment the green light appears, press the button on the front of the smart plug to enter WifiAP mode. You should now see 2 brief consecutive flashes to indicate that the smart plug is in WifiAP mode. </p>
              <p>2. The WIFI Access Point will then appear in your laptop or phones available WIFI networks, the SSID will contain the name smartplug followed by a number e.g: 'smartplug1'. Connect to this network. Once connected click on the following link to open the smartplug configuration interface in a new window: <b><a href="http://192.168.4.1" target="_blank" style="color:#fff">http://192.168.4.1</a></b></p>
              <p>3. Scroll down to the bottom of the configuration interface and click on Factory Reset under the system tab. Restart the smart plug setup process following the guide above.</p>
          </div>
      </div>
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
      $.ajax({ url: emoncmspath+"demandshaper/list"+apikeystr, dataType: 'json', async: true, success: function(result) {
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
                  
                  $("#wizard").hide();
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
    $.ajax({ url: emoncmspath+"device/authcheck.json"+apikeystr, dataType: 'json', async: true, success: function(data) {
        if (typeof data.ip !== "undefined") {
            $("#auth-check-ip").html(data.ip);
            $("#auth-check").show();
            $("#table").css("margin-top","0");
            // $("#no-devices-found-title").html("Device Found");
            // $("#no-devices-found-checking").html("Click Allow to pair device");
        } else {
            $("#table").css("margin-top","3rem");
            $("#auth-check").hide();
        }
    }});
}

$(".auth-check-allow").click(function(){
    var ip = $("#auth-check-ip").html();
    $.ajax({ url: emoncmspath+"device/authallow.json?ip="+ip+apikeystr, dataType: 'json', async: true, success: function(data) {
        $("#auth-check").hide();
        // $("#no-devices-found-checking").html("Please wait for device to connect");
    }});
});

$("#add-device").click(function(event){
    event.preventDefault();
    show_device_finder();
});

function show_device_finder() {
    $("#scheduler-outer").hide();
    $("#wizard").show();
    auth_check();
    clearInterval(auth_check_interval);
    auth_check_interval = setInterval(auth_check,5000);
}

function hide_device_finder() {
    clearInterval(auth_check_interval);
}

$(".wizard-option-l1").click(function(){
   var name = $(this).attr("name");
   $(".wizard-group[name="+name+"]").slideToggle();
   $(".wizard-group[name!="+name+"]").slideUp();
});

$(".wizard-option-l2").click(function(){
   var name = $(this).attr("name");
   $(".wizard-option-l3[name="+name+"]").slideToggle();
   $(".wizard-option-l3[name!="+name+"]").slideUp();
});
</script>
