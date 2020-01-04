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

function get_forecast($redis,$signal,$resolution) {

    $resolution_h = $resolution/3600;
    $divisions = round(24*3600/$resolution);

    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;
    
    // -----------------------------------------------------------------------------   
    $profile = array();
    $available = 1;
    $optimise = MIN;
    
    // -----------------------------------------------------------------------------
    // Grid carbon intensity
    // ----------------------------------------------------------------------------- 
    if ($signal=="carbonintensity") {
        $optimise = MIN;
        
        if (!$result = $redis->get("demandshaper:carbonintensity")) {
            if ($result = http_request("GET","https://emoncms.org/demandshaper/carbonintensity&time=".time(),array())) {
                $redis->set("demandshaper:carbonintensity",$result);
            }
        }
        $result = json_decode($result);
         
        if ($result!=null && isset($result->data)) {
        
            $datetimestr = $result->data[0]->from;
            $date = new DateTime($datetimestr);
            $date->setTimezone(new DateTimeZone("Europe/London"));
            $start = $date->getTimestamp();
            
            $datetimestr = $result->data[count($result->data)-1]->from;
            $date = new DateTime($datetimestr);
            $end = $date->getTimestamp();
        
            for ($timestamp=$start; $timestamp<$end; $timestamp+=$resolution) {
            
                $i = floor(($timestamp - $start)/1800);
                if (isset($result->data[$i])) {
                    $co2intensity = $result->data[$i]->intensity->forecast;
                    
                    $date->setTimestamp($timestamp);
                    $h = 1*$date->format('H');
                    $m = 1*$date->format('i')/60;
                    $hour = $h + $m;
                    
                    if ($timestamp>=$start_timestamp) $profile[] = array($timestamp*1000,$co2intensity,$hour);
                }
            }
        }
    }
    
    // -----------------------------------------------------------------------------
    // Octopus Agile
    // -----------------------------------------------------------------------------
    else if (strpos($signal,"octopusagile_")!==false && strlen($signal)==14) {
        $gsp_id = "A"; if (in_array($signal[13],array("A","B","C","D","E","F","G","H","J","K","L","M","N","P"))) $gsp_id = $signal[13];
    
        $optimise = MIN;
        //$result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));
        // 1. Fetch Octopus forecast
        if (!$result = $redis->get("demandshaper:octopusagile_$gsp_id")) {
            if ($result = http_request("GET","https://emoncms.org/demandshaper/octopus?gsp=$gsp_id&time=".time(),array())) {
                $redis->set("demandshaper:octopusagile_$gsp_id",$result);
            }
        }
        $result = json_decode($result);
        $start = $timestamp; // current time
        $td = 0;
        
        // if forecast is valid
        if ($result!=null && isset($result->results)) {
            
            // sort octopus forecast into time => price associative array
            $octopus = array();
            foreach ($result->results as $row) {
                $date = new DateTime($row->valid_from);
                $date->setTimezone(new DateTimeZone("Europe/London"));
                $octopus[$date->getTimestamp()] = $row->value_inc_vat;
            }
            
            $timestamp = $start_timestamp;
            for ($i=0; $i<$divisions; $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if (isset($octopus[$timestamp])) {
                    $price = $octopus[$timestamp]; 
                } else if (isset($octopus[$timestamp-(24*3600)])) {
                    $price = $octopus[$timestamp-(24*3600)]; 
                } else {
                    $price = 12.0;
                }
                
                $profile[] = array($timestamp*1000,$price,$hour);
                $timestamp += $resolution; 
            }
        }
    }

    // -----------------------------------------------------------------------------
    // EnergyLocal demand shaper
    // -----------------------------------------------------------------------------  
    else if ($signal=="energylocal_bethesda") {
        $optimise = MIN;
        if (!$result = $redis->get("demandshaper:energylocal_bethesda")) {
            if ($result = http_request("GET","https://dashboard.energylocal.org.uk/cydynni/demandshaper?time=".time(),array())) {
                $redis->set("demandshaper:energylocal_bethesda",$result);
            }
        }
        $result = json_decode($result);
        // Validate demand shaper
        if  ($result!=null && isset($result->DATA) && count($result->DATA)>0) {
            
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone("Europe/London"));
            
            $EL_signal = $result->DATA[0];
            array_shift($EL_signal);
            $len = count($EL_signal);

            //------------------------
            // Normalise into 0.0 to 1.0
            $min = 1000; $max = -1000;
            for ($i=0; $i<$len; $i++) {
                $val = (float) $EL_signal[$i];
                if ($val>$max) $max = $val;
                if ($val<$min) $min = $val;
            }
            
            $tmp = array();
            $max = $max += -1*$min;
            for ($i=0; $i<$len; $i++) $tmp[$i*0.5] = (($EL_signal[$i] + -1*$min) / $max);
            $EL_signal = $tmp;
            
            $value = 0.5;
            $timestamp = $start_timestamp;
            for ($i=0; $i<$divisions; $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if (isset($EL_signal[$hour])) $value = $EL_signal[$hour];
                
                $profile[] = array($timestamp*1000,$value,$hour);
                $timestamp += $resolution; 
            }
        }
    }
    // -----------------------------------------------------------------------------
    // Economy 7 
    // ----------------------------------------------------------------------------- 
    else if ($signal=="economy7") {
    
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("Europe/London"));
        
        $optimise = MIN;
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;
            
            $profile[] = array($timestamp*1000,$economy7,$hour);
            $timestamp += $resolution; 
        }
    }

    // -----------------------------------------------------------------------------
    // Nordpool Spot FI
    // ----------------------------------------------------------------------------- 
    else if ($signal=="nordpool_fi") {
        $optimise = MIN;
    
        if (!$result = $redis->get("demandshaper:nordpool_fi")) {
            if ($result = http_request("GET","http://tuntihinta.fi/json/hinnat.json",array())) {
                $redis->set("demandshaper:nordpool_fi",$result);
            }
        }
        $result = json_decode($result);
         
        if ($result!=null && isset($result->data)) {

            $timestamp = $start_timestamp;
            
            foreach ($result->data as $row) {

                $arrDate = new DateTime($row->timestamp);
                $arrDate->setTimezone(new DateTimeZone("Europe/London"));                
                $arrTs = $arrDate->getTimestamp();
                    
                if ($arrTs>=$start_timestamp) 
                {
                    $h = 1*$arrDate->format('H');
                    $m = 1*$arrDate->format('i')/60;
                    $hour = $h + $m;
                    
                    $profile[] = array($arrTs*1000,floatval($row->PriceWithVat),$hour);
                }

                $timestamp += $resolution; 
            }
        }
    }

    // get max and min values of profile
    $min = 1000000; $max = -1000000;
    for ($i=0; $i<count($profile); $i++) {
        $val = (float) $profile[$i][1];
        if ($val>$max) $max = $val;
        if ($val<$min) $min = $val;
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = $optimise;
    $result->min = $min;
    $result->max = $max;
    $result->resolution = $resolution;
    return $result;
}

// -------------------------------------------------------------------------------------------------------
// SCHEDULE
// -------------------------------------------------------------------------------------------------------

function schedule_smart($forecast,$timeleft,$end,$interruptible,$resolution,$device)
{
    $debug = 0;
    $forecast_length = count($forecast->profile) > 24 ? 24 : count($forecast->profile);

    $resolution_h = $resolution/3600;
    $divisions = round($forecast_length*3600/$resolution);
    
    // period is in hours
    $period = $timeleft / 3600;
    if ($period<0) $period = 0;
    
    // Start time
    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));
    
    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = 1*$date->format('i')/60;
    $start_hour = $h + $m;
    
    // End time
    $end = floor($end / $resolution_h) * $resolution_h;
    $date->modify("midnight");
    $end_timestamp = $date->getTimestamp() + $end*3600;
    if ($end_timestamp<$now) $end_timestamp+=3600*$forecast_length;

    $profile = $forecast->profile;

    // --------------------------------------------------------------------------------
    // Upsample profile
    // -------------------------------------------------------------------------------
    $upsampled = array();            
    
    $profile_start = $profile[0][0]*0.001;
    $profile_end = $profile[count($profile)-1][0]*0.001;

    for ($timestamp=$profile_start; $timestamp<$profile_end; $timestamp+=$resolution) {
        $i = floor(($timestamp - $profile_start)/$forecast->resolution);
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
             for ($i=0; $i<$period*($divisions/$forecast_length); $i++) {
                 
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
            $tstart = $profile[$pos][0]*0.001;
            
            if($device === "openevse") {
                $localtime = localtime($tstart);            
                $start_hour = $localtime[2] + $localtime[1]/60;
            }
            else{
                $start_hour = $profile[$pos][2];
            }
        }
        $end_hour = $start_hour;
        $tend = $tstart;
        
        for ($i=0; $i<$period*($divisions/$forecast_length); $i++) {
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
        for ($p=0; $p<$period*($divisions/$forecast_length); $p++) {

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

function schedule_timer($forecast,$start1,$stop1,$start2,$stop2,$resolution) {
    
    $tstart1 = 0; $tstop1 = 0;
    $tstart2 = 0; $tstop2 = 0;

    $profile_start = $forecast->profile[0][0]*0.001;
    $profile_end = $forecast->profile[count($forecast->profile)-1][0]*0.001;

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));
    
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
