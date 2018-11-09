<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

function schedule($redis,$schedule)
{   
    $debug = 0;
    
    $end_time = $schedule->end;
    $period = $schedule->period;
    $interruptible = $schedule->interruptible;
    
    // Default demand shaper: carbon intensity
    $signal = "carbonintensity";
    if (isset($schedule->signal)) $signal = $schedule->signal;
    
    // Basic mode
    if (isset($schedule->basic) && $schedule->basic) {
        $periods = array();
        $start = $schedule->end - $schedule->period;
        $end = $schedule->end;
        $periods[] = array("start"=>$start, "end"=>$end);
        return $periods;
    }

    $timestamp = floor(time()/1800)*1800;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));

    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = 1*$date->format('i')/60;
    $start_hour = $h + $m;
    
    $end_time = floor($end_time / 0.5)*0.5;
    
    // -----------------------------------------------------------------------------   
    $forecast = array();
    $available = 1;
    define("MAX",1);
    define("MIN",0);
    
    // -----------------------------------------------------------------------------
    // Grid carbon intensity
    // ----------------------------------------------------------------------------- 
    if ($signal=="carbonintensity") {
        $optimise = MIN;
        $start = $date->format('Y-m-d\TH:i\Z');
        //$result = json_decode(file_get_contents("https://api.carbonintensity.org.uk/intensity/$start/fw24h"));
        $result = json_decode(file_get_contents("https://emoncms.org/demandshaper/carbonintensity"));
        if ($result!=null && isset($result->data)) {
            for ($i=0; $i<count($result->data); $i++) {
            
                $datetimestr = $result->data[$i]->from;
                $co2intensity = $result->data[$i]->intensity->forecast;
                
                $date = new DateTime($datetimestr);
                $timestamp = $date->getTimestamp();
                
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if ($hour==$end_time && $start_hour!=$end_time) $available = 0;
                
                $forecast[] = array($timestamp*1000,$co2intensity,$hour,$available,0);
            }
        }
    }
    
    // -----------------------------------------------------------------------------
    // Octopus
    // ----------------------------------------------------------------------------- 
    if ($signal=="octopus") {
        $optimise = MIN;
        //$result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));
        $result = json_decode(file_get_contents("https://emoncms.org/demandshaper/octopus"));
        $start = $timestamp;
        $hh = 0;

        if ($result!=null && isset($result->results)) {
            for ($i=count($result->results)-1; $i>0; $i--) {
            
                $datetimestr = $result->results[$i]->valid_from;
                $co2intensity = $result->results[$i]->value_inc_vat;
                
                $date = new DateTime($datetimestr);
                $timestamp = $date->getTimestamp();
                if ($timestamp>=$start && $hh<48) {
                    
                    $h = 1*$date->format('H');
                    $m = 1*$date->format('i')/60;
                    $hour = $h + $m;
                    
                    if ($hour==$end_time && $start_hour!=$end_time) $available = 0;
                    
                    $forecast[] = array($timestamp*1000,$co2intensity,$hour,$available,0);
                    $hh++;
                }
            }
        }
    }

    // -----------------------------------------------------------------------------
    // EnergyLocal demand shaper
    // -----------------------------------------------------------------------------  
    else if ($signal=="cydynni") {
        $optimise = MAX;
        $result = json_decode($redis->get("demandshaper"));
        
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
            
            for ($i=0; $i<48; $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if ($hour==$end_time && $start_hour!=$end_time) $available = 0;
                
                $forecast[] = array($timestamp*1000,$EL_signal[$hour],$hour,$available,0);
                $timestamp += 1800; 
            }
        }
    // -----------------------------------------------------------------------------
    // Economy 7 
    // ----------------------------------------------------------------------------- 
    } else if ($signal=="economy7") {
        $optimise = MIN;
        for ($i=0; $i<48; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;
            
            if ($hour==$end_time && $start_hour!=$end_time) $available = 0;
            
            $forecast[] = array($timestamp*1000,$economy7,$hour,$available,0);
            $timestamp += 1800; 
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
        
        // For each half hour in profile
        for ($hh=0; $hh<48; $hh++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             $sum = 0;
             $valid_block = 1;
             for ($i=0; $i<$period*2; $i++) {
                 
                 if (isset($forecast[$hh+$i])) {
                     if (!$forecast[$hh+$i][3]) $valid_block = 0;
                     $sum += $forecast[$hh+$i][1];
                 }
             }
             
             if ($hh==0) $threshold = $sum;
             
             // Determine the start_time which gives the maximum sum of available power
             if ($valid_block) {
                 if (($optimise==MIN && $sum<$threshold) || ($optimise==MAX && $sum>$threshold)) {
                     $threshold = $sum;
                     $pos = $hh;
                 }
             }
        }
        
        $start_hour = 0;
        if (isset($forecast[$pos])) $start_hour = $forecast[$pos][2];
        $end_hour = $start_hour;
        
        for ($i=0; $i<$period*2; $i++) {
            $forecast[$pos+$i][4] = 1;
            $end_hour+=0.5;
            if ($end_hour>=24) $end_hour -= 24;
            // dont allow to run past end time
            if ($end_hour==$end_time) break;
        }
        
        $periods = array();
        $periods[] = array("start"=>$start_hour, "end"=>$end_hour);
        
        return array("periods"=>$periods,"probability"=>$forecast);

    } else {
        // ---------------------------------------------------------------------------------
        // Method 2: Fill into times of most available power first
        // ---------------------------------------------------------------------------------

        // For each hour of demand
        for ($p=0; $p<$period*2; $p++) {

            if ($optimise==MIN) $threshold = $forecast_max; else $threshold = $forecast_min;
            $pos = -1;
            // for each hour in probability profile
            for ($hh=0; $hh<48; $hh++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && $val>$max
                $val = $forecast[$hh][1];
                
                if ($forecast[$hh][3] && !$forecast[$hh][4]) {
                    if (($optimise==MIN && $val<=$threshold) || ($optimise==MAX && $val>=$threshold)) {
                        $threshold = $val;
                        $pos = $hh;
                    }
                }
            }
            
            // Allocate hour with maximum amount of available power
            if ($pos!=-1) $forecast[$pos][4] = 1;
        }
                
        $periods = array();
        
        $start = null;
        
        $i = 0;
        $last = 0;
        for ($hh=0; $hh<48; $hh++) {
            $hour = $forecast[$hh][2];
            $val = $forecast[$hh][4];
        
            if ($i==0) {
                if ($val) $start = $hour;
                $last = $val;
            }
            
            if ($last==0 && $val==1) {
                $start = $hour;
            }
            
            if ($last==1 && $val==0) {
                $end = $hour*1;
                $periods[] = array("start"=>$start, "end"=>$end);
            }
            
            $last = $val;
            $i++;
        }
        
        if ($last==1) {
            $end = $hour+0.5;
            $periods[] = array("start"=>$start, "end"=>$end);
        }
        
        return array("periods"=>$periods,"probability"=>$forecast);
    }
}
