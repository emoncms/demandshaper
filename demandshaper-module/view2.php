<?php global $path; $v=1; ?>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/demandshaper/demandshaper.css?v=<?php echo $v; ?>">

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/forecast_builder.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/get_device_state.js"></script>
<div id="scheduler-top"></div>

<div id="scheduler-outer">
  <div class="delete-device"><i class="icon-trash icon-white"></i></div>
  <div class="node-scheduler-title"><span class="title-icon"></span><span class="custom-name"></span><span class="device-name">My Device</span> <span class='device-state-message'></span></div>
  <div class="node-scheduler" node="">

    <div class="scheduler-inner">
      <div class="scheduler-inner2">
        <div class="scheduler-controls" style="text-align:center">
        
          <!---------------------------------------------------------------------------------------------------------------------------->
          <!-- CONTROLS -->
          <!---------------------------------------------------------------------------------------------------------------------------->                
          <div id="mode" class="btn-group">
            <button mode="on">On</button><button mode="off">Off</button><button mode="smart" class="active">Smart</button><button mode="timer">Timer</button>
          </div><br><br>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="smart">
          
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span4" style="margin-bottom:0px">
                <div id="run_period">
                  <p>Run period:</p>
                  <div id="period" class="btn-group input-time">
                    <button>-</button><input type="time" val="00:00"><button>+</button>
                  </div>
                </div>
              </div>
              <div class="span4" style="margin-bottom:0px">
                <p>Complete by:</p>
                <div id="end" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4" style="margin-bottom:20px">
                <p>Ok to interrupt:</p>
                <div name="interruptible" state=0 class="input scheduler-checkbox" style="margin:0 auto"></div>
              </div>
            </div>
            
            <br>
          
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="timer hide">
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span2">
                <br><br>
                <p>Timer 1</p>
              </div>
              <div class="span4">
                <p>Start</p>
                <div id="timer_start1" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4">
                <p>Stop</p>
                <div id="timer_stop1" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
            </div>
            
            <br>
            
            <div class="row timer hide" style="max-width:700px; margin: 0 auto;">
              <div class="span2">
                <br><br>
                <p>Timer 2</p>
              </div>
              <div class="span4">
                <p>Start</p>
                  <div id="timer_start2" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4">
                <p>Stop</p>
                <div id="timer_stop2" class="btn-group input-time">
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
          <div id="schedule-co2" style="font-size:14px; color:#888;"></div>
          
          <table class="table">
            <tr><th>Forecast name</th><th>Parameters</th><th>Weight</th><th></th></tr>
            <tbody id="forecasts"></tbody>
          </table>
          <div class="input-prepend input-append"><span class="add-on">Add forecast</span><select id="forecast_list"></select></div>
      
      </div> <!-- schedule-inner2 -->
    </div> <!-- schedule-inner -->
    <div id="ip_address">IP Address: 192.168.1.20</div>
  </div> <!-- node-scheduler -->
</div> <!-- table -->
</div>
<script>var forecast_list = <?php echo json_encode($forecast_list); ?>;</script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/view.js"></script>
