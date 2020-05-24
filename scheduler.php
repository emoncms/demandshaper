<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

define("MAX",1);
define("MIN",0);

// -------------------------------------------------------------------------------------------------------
// FETCH AND PRE-PROCESS FORECASTS AS 24H FROM CURRENT TIME
// -------------------------------------------------------------------------------------------------------

function get_forecast($redis,$signal,$timezone) {

    $demandshaper_dir = "/opt/emoncms/modules/demandshaper";
    require $demandshaper_dir."/cli/settings.php";

    $params = new stdClass();
    $params->timezone = $timezone;
    $params->resolution = 1800;

    $now = time();
    $timestamp = floor($now/$params->resolution)*$params->resolution;
    $params->start = $timestamp;
    
    switch ($signal)
    {
        case "carbonintensity":
            break;
            
        case "economy7":
            break;
            
        case "octopusagile":
            $params->gsp_id = "D";
            break;

        case "energylocal":
            $params->club = "bethesda";
            break;

        case "nordpool":
            $params->area = "DK1";
            $params->signal_token = $nordpool_token;
            break;

        case "solcast":
            $params->siteid = $solcast_siteid;
            $params->api_key = $solcast_apikey;
            break;

        case "solarclimacell":
            $params->lat = "56.782122";
            $params->lon = "-7.630868";
            $params->apikey = $climacell_apikey;
            break;
    }
    
    if (file_exists("$demandshaper_dir/forecasts/$signal.php")) {
        require_once "$demandshaper_dir/forecasts/$signal.php";
        $forecast_fn = "get_forecast_$signal";
        $forecast = $forecast_fn($redis,$params);
    } else {
        $forecast = new stdClass();
    }
    
    // if empty profile create flat line
    $date = new DateTime();
    if (count($forecast->profile)==0) {
        $forecast->optimise = MIN;
        $divisions = round(24*3600/$params->resolution);
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            $forecast->profile[] = array($timestamp*1000,0.15,$hour);
            $timestamp += $params->resolution; 
        }
    }

    // get max and min values of profile
    $forecast->min = 1000000; $forecast->max = -1000000;
    for ($i=0; $i<count($forecast->profile); $i++) {
        $val = (float) $forecast->profile[$i][1];
        if ($val>$forecast->max) $forecast->max = $val;
        if ($val<$forecast->min) $forecast->min = $val;
    }
    
    return $forecast;
}

// -------------------------------------------------------------------------------------------------------
// SCHEDULE
// -------------------------------------------------------------------------------------------------------

function schedule_smart($forecast,$timeleft,$end,$interruptible,$resolution,$timezone)
{
    $debug = 0;
    
    $resolution_h = $resolution/3600;
    $divisions = round(24*3600/$resolution);
    
    // period is in hours
    $period = $timeleft / 3600;
    if ($period<0) $period = 0;
    
    // Start time
    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));
    
    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = 1*$date->format('i')/60;
    $start_hour = $h + $m;
    
    // End time
    $end = floor($end / $resolution_h) * $resolution_h;
    $date->modify("midnight");
    $end_timestamp = $date->getTimestamp() + $end*3600;
    if ($end_timestamp<$now) $end_timestamp+=3600*24;

    $profile = $forecast->profile;

    // --------------------------------------------------------------------------------
    // Upsample profile
    // -------------------------------------------------------------------------------
    $upsampled = array();            
    
    $profile_start = $profile[0][0]*0.001;
    $profile_end = $profile[count($profile)-1][0]*0.001;

    for ($timestamp=$profile_start; $timestamp<$profile_end; $timestamp+=$resolution) {
        $i = floor(($timestamp - $profile_start)/1800);
        if (isset($profile[$i])) {
            $value = $profile[$i][1];
            
            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            $upsampled[] = array($timestamp*1000,$value,$hour);
        }
    }            
    $profile = $upsampled;
    // --------------------------------------------------------------------------------
    
    // No half hours allocated yet
    for ($td=0; $td<count($profile); $td++) {
        $profile[$td][3] = 0;
    }

    if (!$interruptible) 
    {

        // We are trying to find the start time that results in the maximum sum of the available power
        // $max is used to find the point in the forecast that results in the maximum sum..
        $threshold = 0;

        // When $max available power is found, $start_time is set to this point
        $pos = 0;

        // ---------------------------------------------------------------------------------
        // Method 1: move fixed period of demand over probability function to find best time
        // ---------------------------------------------------------------------------------
        
        // For each time division in profile
        for ($td=0; $td<count($profile); $td++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             $sum = 0;
             $valid_block = 1;
             for ($i=0; $i<$period*($divisions/24); $i++) {
                 
                 if (isset($profile[$td+$i])) {
                     if ($profile[$td+$i][0]*0.001>=$end_timestamp) $valid_block = 0;
                     $sum += $profile[$td+$i][1];
                 } else {
                     $valid_block = 0;
                 }
             }
             
             if ($td==0) $threshold = $sum;
             
             // Determine the start_time which gives the maximum sum of available power
             if ($valid_block) {
                 if (($forecast->optimise==MIN && $sum<$threshold) || ($forecast->optimise==MAX && $sum>$threshold)) {
                     $threshold = $sum;
                     $pos = $td;
                 }
             }
        }
        
        $start_hour = 0;
        $tstart = 0;
        if (isset($profile[$pos])) {
            $start_hour = $profile[$pos][2];
            $tstart = $profile[$pos][0]*0.001;
        }
        $end_hour = $start_hour;
        $tend = $tstart;
        
        for ($i=0; $i<$period*($divisions/24); $i++) {
            $profile[$pos+$i][3] = 1;
            $end_hour+=$resolution/3600;
            $tend+=$resolution;
            if ($end_hour>=24) $end_hour -= 24;
            // dont allow to run past end time
            if ($tend==$end_timestamp) break;
        }
        
        $periods = array();
        if ($period>0) {
            $periods[] = array("start"=>array($tstart,$start_hour), "end"=>array($tend,$end_hour));
        }
        return $periods;

    } else {
        // ---------------------------------------------------------------------------------
        // Method 2: Fill into times of most available power first
        // ---------------------------------------------------------------------------------

        // For each hour of demand
        for ($p=0; $p<$period*($divisions/24); $p++) {

            if ($forecast->optimise==MIN) $threshold = $forecast->max; else $threshold = $forecast->min;
            $pos = -1;
            // for each hour in probability profile
            for ($td=0; $td<count($profile); $td++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && $val>$max
                $val = $profile[$td][1];
                
                if ($profile[$td][0]*0.001<$end_timestamp && !$profile[$td][3]) {
                    if (($forecast->optimise==MIN && $val<=$threshold) || ($forecast->optimise==MAX && $val>=$threshold)) {
                        $threshold = $val;
                        $pos = $td;
                    }
                }
            }
            
            // Allocate hour with maximum amount of available power
            if ($pos!=-1) $profile[$pos][3] = 1;
        }
                
        $periods = array();
        
        $start = null;
        $tstart = null;
        $tend = null;
        
        $i = 0;
        $last = 0;
        for ($td=0; $td<count($profile); $td++) {
            $hour = $profile[$td][2];
            $timestamp = $profile[$td][0]*0.001;
            $val = $profile[$td][3];
        
            if ($i==0) {
                if ($val) {
                    $start = $hour;
                    $tstart = $timestamp;
                }
                $last = $val;
            }
            
            if ($last==0 && $val==1) {
                $start = $hour;
                $tstart = $timestamp;
            }
            
            if ($last==1 && $val==0) {
                $end = $hour*1;
                $tend = $timestamp;
                $periods[] = array("start"=>array($tstart,$start), "end"=>array($tend,$end));
            }
            
            $last = $val;
            $i++;
        }
        
        if ($last==1) {
            $end = $hour+$resolution/3600;
            $tend = $timestamp + $resolution;
            $periods[] = array("start"=>array($tstart,$start), "end"=>array($tend,$end));
        }
        
        return $periods;
    }
}

function schedule_timer($forecast,$start1,$stop1,$start2,$stop2,$resolution,$timezone) {
    
    $tstart1 = 0; $tstop1 = 0;
    $tstart2 = 0; $tstop2 = 0;

    $profile_start = $forecast->profile[0][0]*0.001;
    $profile_end = $forecast->profile[count($forecast->profile)-1][0]*0.001;

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));
    
    for ($td=$profile_start; $td<$profile_end; $td+=$resolution) {
        $date->setTimestamp($td);
        $h = 1*$date->format('H');
        $m = 1*$date->format('i')/60;
        $hour = $h + $m;
       
        if ($hour==$start1) $tstart1 = $td;
        if ($hour==$stop1) $tstop1 = $td;
        if ($hour==$start2) $tstart2 = $td;
        if ($hour==$stop2) $tstop2 = $td;
    }
                  
    // For each time division in profile
    /*for ($td=0; $td<count($forecast->profile); $td++) {
        if ($forecast->profile[$td][2]==$start1) $tstart1 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$stop1) $tstop1 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$start2) $tstart2 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$stop2) $tstop2 = $forecast->profile[$td][0]*0.001;
    }*/

    if ($tstart1>$tstop1) $tstart1 -= 3600*24;
    if ($tstart2>$tstop2) $tstart2 -= 3600*24;
           
    $periods = array();
    $periods[] = array("start"=>array($tstart1,$start1), "end"=>array($tstop1,$stop1));
    $periods[] = array("start"=>array($tstart2,$start2), "end"=>array($tstop2,$stop2));
    return $periods;
}
