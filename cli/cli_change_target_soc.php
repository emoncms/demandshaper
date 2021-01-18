<?php
// Load all emoncms and demandshaper requirements
define('EMONCMS_EXEC', 1);
include "/opt/emoncms/modules/demandshaper/cli/load_base.php";

// ------------------
$userid = 1;
$device_name = "openevse";
// ------------------

// Load schedule
$timezone = $user->get_timezone($userid);
$schedules = $demandshaper->get($userid);
$schedule = $schedules->$device_name;
$device_type = $schedule->settings->device_type;

// Change setting
$schedule->settings->target_soc = 0.8;

// Automatic update of time left for schedule e.g take into account updated battery SOC of electric car, home battery, device
$schedule = $device_class[$device_type]->auto_update_timeleft($schedule);

$kwh_required = ($schedule->settings->target_soc-$schedule->settings->current_soc)*$schedule->settings->battery_capacity;

// Print info
print "Current SOC:\t".($schedule->settings->current_soc*100)."%\n";
print "Target SOC:\t".($schedule->settings->target_soc*100)."%\n";
print "SOC Increase:\t".(($schedule->settings->target_soc-$schedule->settings->current_soc)*100)."% x ".$schedule->settings->battery_capacity." kWh battery capacity = ".$kwh_required." kWh\n";
print "Charge:\t\t".($kwh_required)." kWh @ ".$schedule->settings->charge_rate." kW = ".$schedule->settings->period." hrs\n";

// 1. Compile combined forecast
$combined = $demandshaper->get_combined_forecast($schedule->settings->forecast_config,$timezone);
// 2. Calculate forecast min/max 
$combined = forecast_calc_min_max($combined);
// 3. Calculate schedule
if ($schedule->settings->interruptible) {                            
    $schedule->runtime->periods = schedule_interruptible($combined,$schedule->runtime->timeleft,$schedule->settings->end_timestamp,$timezone);
} else {
    $schedule->runtime->periods = schedule_block($combined,$schedule->runtime->timeleft,$schedule->settings->end_timestamp,$timezone);
}

print "Scheduled periods UTC: ".json_encode($schedule->runtime->periods)."\n";

$schedules->$device_name = $schedule;
$demandshaper->set($userid,$schedules);
