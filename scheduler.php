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
    
    // Basic mode
    if (isset($schedule->basic) && $schedule->basic) {
        $periods = array();
        $start = $schedule->end - $schedule->period;
        $end = $schedule->end;
        $periods[] = array("start"=>$start, "end"=>$end);
        return $periods;
    }
    
    $now = time();
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));
    $date->setTimestamp($now);
    $date->modify("midnight");
    $daystart = $date->getTimestamp();
    
    $seconds = $now - $daystart;
    $minutes = floor($seconds/60);
    $start_hour = $minutes/60;
    
    // limit to half hour resolution
    $start_hour = floor($start_hour*2)/2;
    $end_time = floor($end_time*2)/2;
    
    // 24h dummy data
    //                      0   1   2   3   4   5   6   7   8   9   10  11  12  13  14  15  16  17  18  19  20  21  22  23
    // $probability = array(0.7,1.0,1.0,0.8,0.6,0.4,0.2,0.2,0.3,0.5,0.6,0.6,0.7,0.8,0.7,0.6,0.5,0.4,0.3,0.1,0.1,0.3,0.5,0.6);
    
    // 24h HH dummy data
    $probability = array(0.80,0.83,0.85,0.87,0.9,0.93,0.95,0.97,1.0,1.0,0.75,0.7,0.4,0.4,0.3,0.3,0.2,0.2,0.3,0.3,0.4,0.4,0.55,0.55,
                         0.63,0.64,0.65,0.65,0.7,0.7,0.7,0.7,0.5,0.5,0.4,0.4,0.3,0.3,0.15,0.15,0.1,0.1,0.3,0.3,0.5,0.5,0.6,0.6);
    

    
    // -----------------------------------------------------------------------------
    // Fetch demand shaper
    // -----------------------------------------------------------------------------

    $result = json_decode($redis->get("demandshaper"));
    $probability = $result->DATA[0];
    array_shift($probability);

    $len = count($probability);

    // Normalise into 0.0 to 1.0
    $min = 1000; $max = -1000;
    for ($i=0; $i<$len; $i++) {
        if ($probability[$i]>$max) $max = $probability[$i];
        if ($probability[$i]<$min) $min = $probability[$i];
    }
    $max = $max += -1*$min;
    for ($i=0; $i<$len; $i++) $probability[$i] = 1.0 - (($probability[$i] + -1*$min) / $max);
    
    
    // transpose include keys
    $tmp = array();               
    for ($i=0; $i<48; $i++) $tmp["".($i*0.5)] = $probability[$i];
    $probability = $tmp;

    // generate array of half hours from start time to end time.
    $tmp2 = array();
    $hour = $start_hour;
    $timestamp = $daystart+($hour*3600);
    $available = 1;
              
    for ($i=0; $i<48; $i++)
    {
        if ($hour==$end_time && $start_hour!=$end_time) $available = 0;
        
        $state = 0;
    
        $tmp2[] = array($timestamp*1000,$probability[$hour],$hour,$available,$state);
        $timestamp += 1800;
        $hour += 0.5;
        if ($hour>23.5) $hour = 0.0;
    }
    $forecast = $tmp2;

    if (!$interruptible) 
    {

        // We are trying to find the start time that results in the maximum sum of the available power
        // $max is used to find the point in the forecast that results in the maximum sum..
        $max = 0;

        // When $max available power is found, $start_time is set to this point
        $pos = 0;

        // ---------------------------------------------------------------------------------
        // Method 1: move fixed period of demand over probability function to find best time
        // ---------------------------------------------------------------------------------
        
        // For each half hour in profile
        for ($hh=0; $hh<48; $hh++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             $sum = 0;
             for ($i=0; $i<$period*2; $i++) {
                 if (isset($forecast[$hh+$i]) && $forecast[$hh+$i][3]) $sum += $forecast[$hh+$i][1];
             }
             
             // Determine the start_time which gives the maximum sum of available power
             if ($sum>$max) {
                 $max = $sum;
                 $pos = $hh;
             }
        }
        
        $start_hour = $forecast[$pos][2];
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

            $max = 0;
            $pos = -1;
            // for each hour in probability profile
            for ($hh=0; $hh<48; $hh++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && $val>$max
                $val = $forecast[$hh][1];
                
                if ($forecast[$hh][3] && !$forecast[$hh][4] && $val>$max) {
                    $max = $val;
                    $pos = $hh;
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
