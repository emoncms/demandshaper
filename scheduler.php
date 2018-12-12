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

function schedule($redis,$schedule)
{   
    $resolution = 1800;
    $resolution_h = $resolution/3600;
    $divisions = round(24*3600/$resolution);
    
    $debug = 0;
    if (!isset($schedule->timeleft)) $schedule->timeleft = 0;
    
    $end_time = $schedule->end;
    $period = $schedule->timeleft / 3600;
    if ($period<0) $period = 0;
    $interruptible = $schedule->interruptible;
    
    // Default demand shaper: carbon intensity
    $signal = "carbonintensity";
    if (isset($schedule->signal)) $signal = $schedule->signal;
    
    // Basic mode
    // if (isset($schedule->basic) && $schedule->basic) {
    //    $periods = array();
    //    $start = $end_time - $period;
    //    $end = $end_time;
    //    $periods[] = array("start"=>$start, "end"=>$end);
    //    return $periods;
    // }
    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));

    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = 1*$date->format('i')/60;
    $start_hour = $h + $m;
    
    // -----------------------------------------------------------------------------
    // Convert end time to timestamp
    $end_time = floor($end_time / $resolution_h) * $resolution_h;
    
    $date->modify("midnight");
    $end_timestamp = $date->getTimestamp() + $end_time*3600;
    if ($end_timestamp<$now) $end_timestamp+=3600*24;
    $schedule->end_timestamp = $end_timestamp;
    
    // -----------------------------------------------------------------------------   
    $forecast = array();
    $available = 1;

    
    // -----------------------------------------------------------------------------
    // Grid carbon intensity
    // ----------------------------------------------------------------------------- 
    if ($signal=="carbonintensity") {
        $optimise = MIN;
        // $start = $date->format('Y-m-d\TH:i\Z');
        // $result = json_decode(file_get_contents("https://api.carbonintensity.org.uk/intensity/$start/fw24h"));
        $result = json_decode($redis->get("demandshaper:carbonintensity"));
        
        if ($result!=null && isset($result->data)) {
        
            $datetimestr = $result->data[0]->from;
            $date = new DateTime($datetimestr);
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
                    
                    if ($timestamp>=$end_timestamp) $available = 0;
                    if ($timestamp>=$start_timestamp) $forecast[] = array($timestamp*1000,$co2intensity,$hour,$available,0);
                }
            }
        }
    }
    
    // -----------------------------------------------------------------------------
    // Octopus
    // ----------------------------------------------------------------------------- 
    if ($signal=="octopus") {
        $optimise = MIN;
        //$result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));
        $result = json_decode($redis->get("demandshaper:octopus"));
        $start = $timestamp;
        $td = 0;

        if ($result!=null && isset($result->results)) {
            for ($i=count($result->results)-1; $i>0; $i--) {
            
                $datetimestr = $result->results[$i]->valid_from;
                $co2intensity = $result->results[$i]->value_inc_vat;
                
                $date = new DateTime($datetimestr);
                $timestamp = $date->getTimestamp();
                if ($timestamp>=$start && $td<48) {
                    
                    $h = 1*$date->format('H');
                    $m = 1*$date->format('i')/60;
                    $hour = $h + $m;
                    
                    if ($timestamp>=$end_timestamp) $available = 0;
                    if ($timestamp>=$start_timestamp) $forecast[] = array($timestamp*1000,$co2intensity,$hour,$available,0);
                    $td++;
                }
            }
        }
    }

    // -----------------------------------------------------------------------------
    // EnergyLocal demand shaper
    // -----------------------------------------------------------------------------  
    else if ($signal=="cydynni") {
        $optimise = MAX;
        $result = json_decode($redis->get("demandshaper:bethesda"));
        
        // Validate demand shaper
        if  ($result!=null && isset($result->DATA)) {
       
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
            for ($i=0; $i<$len; $i++) $tmp[$i*0.5] = 1.0 - (($EL_signal[$i] + -1*$min) / $max);
            $EL_signal = $tmp;
            
            //------------------------
            
            for ($i=0; $i<count($EL_signal); $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if ($timestamp>=$end_timestamp) $available = 0;
                $forecast[] = array($timestamp*1000,$EL_signal[$hour],$hour,$available,0);
                $timestamp += 1800; 
            }
        }
    // -----------------------------------------------------------------------------
    // Economy 7 
    // ----------------------------------------------------------------------------- 
    } else if ($signal=="economy7") {
        $optimise = MIN;
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;
            
            if ($timestamp>=$end_timestamp) $available = 0;
            $forecast[] = array($timestamp*1000,$economy7,$hour,$available,0);
            $timestamp += $resolution; 
        }
    }
    
    // get max and min values of forecast
    $forecast_min = 1000000; $forecast_max = -1000000;
    for ($i=0; $i<count($forecast); $i++) {
        $val = (float) $forecast[$i][1];
        if ($val>$forecast_max) $forecast_max = $val;
        if ($val<$forecast_min) $forecast_min = $val;
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
        for ($td=0; $td<count($forecast); $td++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             $sum = 0;
             $valid_block = 1;
             for ($i=0; $i<$period*($divisions/24); $i++) {
                 
                 if (isset($forecast[$td+$i])) {
                     if (!$forecast[$td+$i][3]) $valid_block = 0;
                     $sum += $forecast[$td+$i][1];
                 }
             }
             
             if ($td==0) $threshold = $sum;
             
             // Determine the start_time which gives the maximum sum of available power
             if ($valid_block) {
                 if (($optimise==MIN && $sum<$threshold) || ($optimise==MAX && $sum>$threshold)) {
                     $threshold = $sum;
                     $pos = $td;
                 }
             }
        }
        
        $start_hour = 0;
        $tstart = 0;
        if (isset($forecast[$pos])) {
            $start_hour = $forecast[$pos][2];
            $tstart = $forecast[$pos][0]*0.001;
        }
        $end_hour = $start_hour;
        $tend = $tstart;
        
        for ($i=0; $i<$period*($divisions/24); $i++) {
            $forecast[$pos+$i][4] = 1;
            $end_hour+=$resolution/3600;
            $tend+=$resolution;
            if ($end_hour>=24) $end_hour -= 24;
            // dont allow to run past end time
            if ($end_hour==$end_time) break;
        }
        
        $periods = array();
        $periods[] = array("start"=>array($tstart,$start_hour), "end"=>array($tend,$end_hour));
        
        return array("periods"=>$periods,"probability"=>$forecast);

    } else {
        // ---------------------------------------------------------------------------------
        // Method 2: Fill into times of most available power first
        // ---------------------------------------------------------------------------------

        // For each hour of demand
        for ($p=0; $p<$period*($divisions/24); $p++) {

            if ($optimise==MIN) $threshold = $forecast_max; else $threshold = $forecast_min;
            $pos = -1;
            // for each hour in probability profile
            for ($td=0; $td<count($forecast); $td++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && $val>$max
                $val = $forecast[$td][1];
                
                if ($forecast[$td][3] && !$forecast[$td][4]) {
                    if (($optimise==MIN && $val<=$threshold) || ($optimise==MAX && $val>=$threshold)) {
                        $threshold = $val;
                        $pos = $td;
                    }
                }
            }
            
            // Allocate hour with maximum amount of available power
            if ($pos!=-1) $forecast[$pos][4] = 1;
        }
                
        $periods = array();
        
        $start = null;
        $tstart = null;
        $tend = null;
        
        $i = 0;
        $last = 0;
        for ($td=0; $td<count($forecast); $td++) {
            $hour = $forecast[$td][2];
            $timestamp = $forecast[$td][0]*0.001;
            $val = $forecast[$td][4];
        
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
        
        return array("periods"=>$periods,"probability"=>$forecast);
    }
}
