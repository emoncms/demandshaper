<?php
global $path;
load_css("Modules/demandshaper/demandshaper.css");
load_js("Lib/js/flot-5.1.0.mod.min.js");
load_js("Modules/demandshaper/js/forecast_builder.js");
load_js("Modules/demandshaper/js/get_device_state.js");
load_js("Modules/demandshaper/js/battery.js");
load_js("Modules/demandshaper/js/openevse.js");
load_js("Modules/demandshaper/js/hpmon.js");
?>
<div id="scheduler-top"></div>

<div id="scheduler-outer">
  <div class="delete-device"><i class="icon-trash icon-white"></i></div>
  <div class="node-scheduler-title"><span class="title-icon"></span><span class="custom-name"></span><span class="device-name"></span> <span class='device-state-message'></span></div>
  <div class="node-scheduler" node="">
    <div class="scheduler-inner">
      <div class="scheduler-inner2">
        <div class="scheduler-controls" style="text-align:center">
        
          <!---------------------------------------------------------------------------------------------------------------------------->
          <!-- CONTROLS -->
          <!---------------------------------------------------------------------------------------------------------------------------->                
          <div id="mode" class="ds-group">
            <button mode="on">On</button><button mode="off">Off</button><button mode="smart" class="active">Smart</button><button mode="timer">Timer</button>
          </div><br><br>
          
          <div class="openevse hide">
            <p>Charge Current <span id="charge_current">0</span>A<br><span style="font-weight:normal; font-size:12px">Temperature <span id="openevse_temperature">10</span>C</span></p>
            <div id="battery_bound" style="width:100%" class="hide">
                <canvas id="battery"></canvas>
            </div>
          </div>
          
          <div class="heatpumpmonitor hide">
            <div class="row g-0 justify-content-center ds-row">
              <div class="col-12 col-md-auto" style="margin-bottom:20px"><br>
                <p>Flow Temperature <span id="heatpump_flowT"></span>C<br><span style="font-weight:normal; font-size:12px">Heat Output <span id="heatpump_heat">0</span>W</span></p>
              </div>
              <div class="col-12 col-md-auto" style="margin-bottom:20px">
                <p>Target Temperature</p>
                <div id="flowT" class="ds-group">
                  <button>-</button><input class="input" name="flowT" type="text" val="0" style="width:60px"><button>+</button>
                </div>
              </div>
            </div>
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="smart">
          
            <div class="row g-0 justify-content-center ds-row">
              <div class="col-12 col-md-auto" style="margin-bottom:8px">
                <div id="run_period">
                  <p>Run period:</p>
                  <div id="period" class="ds-group input-time">
                    <button>-</button><input type="time" val="00:00"><button>+</button>
                  </div>
                </div>
                <div id="charge_energy_div" class="hide">
                  <p>Energy (kWh):</p>
                  <div id="charge_energy" class="ds-group">
                    <button>-</button><input class="input" name="charge_energy" type="text" val="0" style="width:30px; text-align:center"><button>+</button>
                  </div>
                </div>
                <div id="charge_distance_div" class="hide">
                  <p>Distance (<span id="charge_distance_units">miles</span>):</p>
                  <div id="charge_distance" class="ds-group">
                    <button>-</button><input class="input" name="charge_distance" type="text" val="0" style="width:30px; text-align:center"><button>+</button>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-auto" style="margin-bottom:8px">
                <p>Complete by:</p>
                <div id="end" class="ds-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="col-12 col-md-auto" style="margin-bottom:8px">
                <p>Ok to interrupt:</p>
                <div name="interruptible" state=0 class="scheduler-checkbox" style="margin:0 auto"></div>
              </div>
              <div class="col-12 col-md-auto hide" style="margin-bottom:8px">
                <p title="Solar PV Divert">Eco mode:</p>
                <div title="Solar PV Divert" name="divert_mode" state=0 class="scheduler-checkbox" style="margin:0 auto"></div>
              </div>
            </div>
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="timer hide">
            <div class="row g-0 justify-content-center ds-row">
              <div class="col-12 col-md-auto timer-title">
                <p>Timer 1</p>
              </div>
              <div class="col-12 col-md-auto">
                <p>Start</p>
                <div id="timer_start1" class="ds-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="col-12 col-md-auto">
                <p>Stop</p>
                <div id="timer_stop1" class="ds-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
            </div>
            
            <br>
            
            <div class="row g-0 justify-content-center ds-row">
              <div class="col-12 col-md-auto timer-title">
                <p>Timer 2</p>
              </div>
              <div class="col-12 col-md-auto">
                <p>Start</p>
                  <div id="timer_start2" class="ds-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="col-12 col-md-auto">
                <p>Stop</p>
                <div id="timer_stop2" class="ds-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
            </div>
            <br>
          </div>
          
          <div id="schedule-output" style="font-weight:normal; padding-top:20px; padding-bottom:20px"></div>
          <div id="timeleft" style="font-weight:normal; font-size:14px"></div>
          <div id="placeholder_bound" style="width:100%; height:300px">
            <div id="placeholder" style="height:300px"></div>
          </div><br>
          <div id="schedule-info" style="font-size:14px; color:#888;"></div>
        </div> <!-- schedule-controls -->
      </div> <!-- schedule-inner2 -->

        
    </div> <!-- scheduler-inner -->
    
    <div class="scheduler-inner" style="background-color:#eaeaea; font-weight:normal">
      <div class="config-device"><i class="icon-wrench" title="Configure device"></i></div>
      <div id="ip_address">IP Address: ---</div>
    </div>
    
    <div class="scheduler-inner hide" style="background-color:#eaeaea; font-weight:normal">
        <div class="scheduler-config" style="text-align:left">

          <div style="border: 1px solid #ccc; padding:10px; background-color:#f0f0f0;">
          <div style="display:inline-block; width:200px">Device name:</div><input class="device_name form-control input-165" type="text">   
          </div>
        
          <div style="border: 1px solid #ccc; padding:10px; margin-top:10px; background-color:#f0f0f0;">
            <p><b>Forecast Settings</b></p>   
            <table class="table" style="margin-bottom:0px">
              <tr><th>Forecast name</th><th>Parameters</th><th>Weight</th><th></th></tr>
              <tbody id="forecasts"></tbody>
            </table>
            <div class="input-group mb-2"><span class="input-group-text">Add forecast</span><select id="forecast_list" class="form-select input-auto"></select></div><br>
            <div class="input-group"><span class="input-group-text">Schedule info</span><select class="forecast_units form-select input-auto">
              <option value="generic">Generic</option>
              <option value="pkwh">p/kWh</option>
              <option value="gco2">gCO2</option>
            </select></div>   
          </div>
          
          <div class="openevse hide" style="border: 1px solid #ccc; padding:10px; margin-top:10px; background-color:#f0f0f0">
            <p><b>OpenEVSE Settings</b></p>
            <table class="table">
              <tr><td>OpenEVSE IP address:</td><td><input class="input form-control input-165" name="openevse_ip" type="text" placeholder="openevse.local"/></td></tr>
              <tr><td>Control based on:</td><td><select class="input form-select input-auto" name="soc_source"><option value="time">Charge time</option><option value="energy">Charge energy</option><option value="distance">Travel distance</option><option value="input">Battery charge level (Input)</option><option value="ovms">Battery charge level (OVMS)</option><option value="api">set-device-settings api</option></select></td></tr>
              <tr><td>Useable Battery Capacity:</td><td><input class="input form-control input-105" name="battery_capacity" type="text"/> kWh</td></tr>
              <tr><td>AC Charge Rate:</td><td><input class="input form-control input-105" name="charge_rate" type="text"/> kW</td></tr>
              <tr><td>Car economy:</td><td><input class="input form-control input-105" name="car_economy" type="text"/> <span id="car_economy_units">miles/kWh</span></td></tr>
              <tr><td>Distance units:</td><td><select class="input form-select input-105" name="distance_units"><option>miles</option><option>km</option></select></td></tr>
              <tr class="openevse-balancing hide"><td>Balancing Percentage::</td><td><input class="input form-control input-105" name="balpercentage" type="text"/> %</td></tr>
              <tr class="openevse-balancing hide"><td>Balancing Time:</td><td><input class="input form-control input-105" name="baltime" type="text"/> Mins</td></tr>
              <tr class="ovms-options hide"><td>OVMS Vehicle ID:</td><td><input class="input form-control input-105" name="ovms_vehicleid" type="text"/></td></tr>
              <tr class="ovms-options hide"><td>OVMS Car Password:</td><td><input class="input form-control input-105" name="ovms_carpass" type="text"/></td></tr> 
            </table>      
          </div>          

          <div class="general" style="border: 1px solid #ccc; padding:10px; margin-top:10px; background-color:#f0f0f0">
            <p><b>General Settings</b></p>
            <table class="table" style="margin:0">
              <tr><td>At end of smart schedule:</td><td><select class="input form-select input-auto" id="on_completion"><option value="smart">Reschedule</option><option value="off">Turn off</option><option value="on">Turn on</option></select></td></tr>
            </table>      
          </div> 
          
      </div>
    </div> <!-- scheduler-inner -->
  </div> <!-- node-scheduler -->
</div> <!-- scheduler-outer -->

<div id="DeleteDeviceModal" class="modal" tabindex="-1" aria-labelledby="DeleteDeviceModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="feedDeleteModalLabel" class="modal-title">Delete Device: <span class='device-name'></span></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                 <p>Are you sure you want to delete device <span class='device-name'></span>?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-bs-dismiss="modal" aria-hidden="true"><?php echo _('Close'); ?></button>
                <button id="delete-device-confirm" class="btn btn-danger"><?php echo _('Confirm'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
var forecast_list = <?php echo json_encode($forecast_list); ?>;
var schedule = <?php echo json_encode($schedule); ?>;
var device_id = <?php echo $device_id; ?>;
</script>
<?php load_js("Modules/demandshaper/js/main.js"); ?>
