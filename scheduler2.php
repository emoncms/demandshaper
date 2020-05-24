<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

//define("MAX",1);
//define("MIN",0);

// -------------------------------------------------------------------------------------------------------
// SCHEDULE
// -------------------------------------------------------------------------------------------------------

// ---------------------------------------------------------------------------------
// Method 1: move fixed period of demand over forecast profile to find best time
// $forecast object
// $period: length in seconds
// $end: end timestamp
// ---------------------------------------------------------------------------------
function schedule_block($forecast,$period,$end,$timezone)
{    
    $profile = $forecast->profile;
    $profile_length = count($profile);
    
    // End time limits
    if ($end<$forecast->start) return array();
    if ($end>$forecast->end) $end = $forecast->end;
    
    // Period limits
    if ($period<=0) return array();
    if ($period>($profile_length*$forecast->interval)) $period = $profile_length*$forecast->interval;
    if ($period>($end-$forecast->start)) $period = $end-$forecast->start;
    
    // Number of full intervals over which period spans
    $period_div = ceil($period/$forecast->interval);
    
    // We are trying to find the start time that results in the optimum price or power availability
    // $threshold is used to find the point in the forecast that results in the maximum sum..
    $threshold = 0;

    // Holds the optimum starting position
    $pos = -1;
            
    // For each time division in profile
    for ($td=0; $td<$profile_length; $td++) {

         // Calculate sum of probability function values for block of demand covering hours in period
         $sum = 0;
         $valid_block = 1;
         for ($i=0; $i<$period_div; $i++) {
             
             if (isset($profile[$td+$i])) {
                 $time = $forecast->start + (($td+$i)*$forecast->interval);
                 if ($time>=$end) $valid_block = 0;
                 $sum += $profile[$td+$i];
             } else {
                 $valid_block = 0;
             }
         }
         
         if ($td==0) $threshold = $sum;
         
         // Determine the start_time which gives the maximum sum of available power
         if ($valid_block) {
             if (($forecast->optimise==MIN && $sum<=$threshold) || ($forecast->optimise==MAX && $sum>=$threshold)) {
                 $threshold = $sum;
                 $pos = $td;
             }
         }
    }
    
    if ($pos==-1) return array();
    
    // Adjustment for actual period in seconds rather than profile intervals
    // work out whether its better to move the period left of right to get lowest sum
    // given only partial overlap with the left most or right most division
    $left_val = $profile[$pos];
    $right_val = $profile[$pos+($period_div-1)];
    
    if ($left_val<$right_val) {
        $start = $forecast->start + ($pos*$forecast->interval);
        $end = $start + $period;
    } else {
        $end = $forecast->start + (($pos+$period_div)*$forecast->interval);
        $start = $end - $period;
    }
        
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));
    return array(array("start"=>array($start,get_hour($date,$start)), "end"=>array($end,get_hour($date,$end))));
}

// ---------------------------------------------------------------------------------
// Method 2: Fill into times of lowest price or most available power first
// ---------------------------------------------------------------------------------
function schedule_interruptible($forecast,$period,$end,$timezone)
{
    $profile = $forecast->profile;
    $profile_length = count($profile);
    
    // End time limits
    if ($end<$forecast->start) return array();
    if ($end>$forecast->end) $end = $forecast->end;
    
    // Period limits
    if ($period<=0) return array();
    if ($period>($profile_length*$forecast->interval)) $period = $profile_length*$forecast->interval;
    if ($period>($end-$forecast->start)) $period = $end-$forecast->start;
    
    // Number of full intervals over which period spans
    $period_div = ceil($period/$forecast->interval);
    
    $allocated = array();
    for ($td=0; $td<$profile_length; $td++) {
        $allocated[$td] = 0;
    }

    // For each hour of demand
    for ($p=0; $p<$period_div; $p++) {

        if ($forecast->optimise==MIN) $threshold = $forecast->max; else $threshold = $forecast->min;
        $pos = -1;
        // for each hour in probability profile
        for ($td=0; $td<$profile_length; $td++) {
            // Find the hour with the maximum amount of available power
            // that has not yet been alloated to this load
            // if available && !allocated && $val>$max
            $val = $profile[$td];
            
            $time = $forecast->start + ($td*$forecast->interval);
            
            if ($time<$end && !$allocated[$td]) {
                if (($forecast->optimise==MIN && $val<=$threshold) || ($forecast->optimise==MAX && $val>=$threshold)) {
                    $threshold = $val;
                    $pos = $td;
                }
            }
        }
        
        // Allocate hour with maximum amount of available power
        if ($pos!=-1) $allocated[$pos] = 1;
    }
    
    // In order to support schedule periods that are not an integer number of the forecast interval in length (e.g 3630 seconds)
    // We note down the last allocated interval, which is also the least optimum interval.
    $last_allocated = $pos;
    $partial = $period % $forecast->interval;
    
    // We then build an array of segments, each segment is an intervals length or less if we have a partial interval
    $segments = array();
    for ($td=0; $td<$profile_length; $td++) {
        $time = $forecast->start + ($td*$forecast->interval);
        if ($allocated[$td]) {
            // Most intervals should start at the normal interval timestamp and end at the next interval timestamp
            $start = $time;
            $end = $time+$forecast->interval;
            
            // If we have a partial, then we allocate this segment to be nearest to the end of the interval
            if ($partial>0 && $last_allocated==$td) {
                $start = $end - $partial;
            }
            $segments[] = array($start,$end);
        }
    }
    // allocate an dud segment here to make it easier to find the end of the last period in the next section 
    $segments[] = array(0,0);
    
    $periods = array();
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));
    
    // This part is essentially joining together all segments that are next to each other to give us an output
    // that represents continuous periods.
    
    $slen = count($segments);
    $start = $segments[0][0];
    for ($s=0; $s<$slen-1; $s++) {
        // if the end time of this segment is not the same as the start time of the next segment then 
        // these segments cant be joined and so we close off a continuous period at this point.
        if ($segments[$s][1]!=$segments[$s+1][0]) {
            $end = $segments[$s][1];
            $periods[] = array("start"=>array($start,get_hour($date,$start)), "end"=>array($end,get_hour($date,$end)));
            $start = $segments[$s+1][0];
        }
        
    }
    return $periods;
}

function schedule_timer($forecast,$start1,$stop1,$start2,$stop2,$resolution,$timezone) {
    
    $tstart1 = 0; $tstop1 = 0;
    $tstart2 = 0; $tstop2 = 0;

    $profile_start = $forecast->profile[0][0];
    $profile_end = $forecast->profile[count($forecast->profile)-1][0];

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

    if ($tstart1>$tstop1) $tstart1 -= 3600*24;
    if ($tstart2>$tstop2) $tstart2 -= 3600*24;
           
    $periods = array();
    $periods[] = array("start"=>array($tstart1,$start1), "end"=>array($tstop1,$stop1));
    $periods[] = array("start"=>array($tstart2,$start2), "end"=>array($tstop2,$stop2));
    return $periods;
}

function get_hour($date,$timestamp) {
    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = number_format(1*$date->format('i')/60,3,'.','');
    return $h + $m;
}

function forecast_calc_min_max($forecast) {
    // get max and min values of profile
    $forecast->min = 1000000; $forecast->max = -1000000;
    for ($i=0; $i<count($forecast->profile); $i++) {
        $val = (float) $forecast->profile[$i];
        if ($val>$forecast->max) $forecast->max = $val;
        if ($val<$forecast->min) $forecast->min = $val;
    }
    return $forecast;
}
