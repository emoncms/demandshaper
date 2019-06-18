<div id="scheduler-outer">
  <div class="delete-device"><i class="icon-trash icon-white"></i></div>
  <div class="node-scheduler-title"></div>
  <div class="node-scheduler" node="">

    <div class="scheduler-inner">
      <div class="scheduler-inner2">
        <!--<p>Temperature & Humidity node</p>
        <br>-->
        
        <div style="text-align:center">
            <div style="width:50%; float:left; color:#e14040;">
                <p>Temperature</p>
                <div><span id="emonth_temperature" style="font-size:32px"></span>C</div>
            </div>
            <div style="width:50%; float:left; color:#4072e1;">
                <p>Humidity</p>
                <div><span id="emonth_humidity" style="font-size:32px"></span>%</div>
            </div>
            <div style="clear:both"></div>
            <br>
        </div>
        
        <div id="placeholder_bound" style="width:100%; height:300px">
            <div id="placeholder" style="height:300px"></div>
        </div>

        <br>
        <div style="text-align:center">
            <div style="width:33%; float:left">
                <p>Min</p>
                <div>
                  <span id="emonth_temperature_min" style="font-size:24px"></span>C / 
                  <span id="emonth_humidity_min" style="font-size:24px"></span>%
                </div>
            </div>
            <div style="width:33%; float:left">
                <p>Max</p>
                <div><span id="emonth_temperature_max" style="font-size:24px"></span>C / 
                  <span id="emonth_humidity_max" style="font-size:24px"></span>%
                </div>
            </div>
            <div style="width:33%; float:left">
                <p>Mean</p>
                <div><span id="emonth_temperature_mean" style="font-size:24px"></span>C / 
                  <span id="emonth_humidity_mean" style="font-size:24px"></span>%
                </div>
            </div>
            <div style="clear:both"></div>
        </div>
                
      </div> <!-- schedule-inner2 -->
    </div> <!-- schedule-inner -->
  </div> <!-- node-scheduler -->
</div> <!-- table -->
</div>

<div id="DeleteDeviceModal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="DeleteDeviceModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="feedDeleteModalLabel">Delete Device: <span class='device-name'></span></h3>
    </div>
    <div class="modal-body">
         <p>Are you sure you want to delete device <span class='device-name'></span>?</p>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Close'); ?></button>
        <button id="delete-device-confirm" class="btn btn-danger"><?php echo _('Confirm'); ?></button>
    </div>
</div>

<script type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/emonth.js?v=6"></script>
