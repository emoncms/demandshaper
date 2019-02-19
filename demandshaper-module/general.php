<div id="scheduler-outer">
  <div class="node-scheduler-title"></div>
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
          
          <div id="openevse" class="hide">
            <p>Charge Current <span id="charge_current">0</span>A<br><span style="font-weight:normal; font-size:12px">Temperature <span id="openevse_temperature">10</span>C</span></p>
            <div id="battery_bound" style="width:100%">
                <canvas id="battery"></canvas>
            </div>
          </div>

          <div class="heatpumpmonitor hide">
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span4 offset2" style="margin-bottom:20px"><br>
                <p>Flow Temperature <span id="heatpump_flowT"></span>C<br><span style="font-weight:normal; font-size:12px">Heat Output <span id="heatpump_heat"></span>W</span></p>
              </div>
              <div class="span4" style="margin-bottom:20px">
                <p>Target Temperature</p>
                <div id="flowT" class="btn-group input-temperature">
                  <button>-</button><input type="text" val="0" style="width:60px"><button>+</button>
                </div>
              </div>
            </div>
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="smart">
          
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span4" style="margin-bottom:20px">
                <div id="run_period">
                  <p>Run period:</p>
                  <div id="period" class="btn-group input-time">
                    <button>-</button><input type="time" val="00:00"><button>+</button>
                  </div>
                </div>
              </div>
              <div class="span4" style="margin-bottom:20px">
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
          <!---------------------------------------------------------------------------------------------------------------------------->
          
          <div class="repeat">
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
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
        
          <div style="height:50px"></div>
          
          <div id="schedule-output" style="font-weight:normal"></div>
          <div id="timeleft" style="font-weight:normal; font-size:14px"></div>
          <div id="placeholder_bound" style="width:100%; height:300px">
            <div id="placeholder" style="height:300px"></div>
          </div><br>
          <div id="schedule-co2" style="font-size:14px; color:#888;"></div>

          <!--
          <br>
          <span class="">Demand shaper signal: </span>
          <select name="signal" class="input scheduler-select" style="margin-top:10px">
              <option value="carbonintensity">UK Grid Carbon Intensity</option>
              <option value="octopus">Octopus Agile (D)</option>
              <option value="cydynni">Energy Local: Bethesda</option>
              <option value="economy7">Economy 7</option>
          </select>
        -->
      </div> <!-- schedule-inner2 -->
    </div> <!-- schedule-inner -->
  </div> <!-- node-scheduler -->
</div> <!-- table -->

<script type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/general.js?v=4"></script>
