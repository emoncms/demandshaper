<?php

function time_offset($t,$timeOffset) {
    $t += $timeOffset;
    if ($t<0.0) $t += 24.0;
    if ($t>=24.0) $t -= 24.0;
    return $t;
}

function time_conv($t,$timeOffset){
    $t = floor($t*0.01) + ($t*0.01 - floor($t*0.01))/0.6;
    $t += $timeOffset;
    if ($t<0.0) $t += 24.0;
    if ($t>=24.0) $t -= 24.0;
    return $t;
}

function conv_time($time,$timeOffset) {
    $h = floor($time*0.01);
    $m = (($time*0.01) - $h)/0.6;
    $t = $h+$m+$timeOffset;
    if ($t<0.0) $t += 24.0;
    if ($t>=24.0) $t -= 24.0;
    return $t;
}

function time_conv_dec_str($t,$div="") {
    $h = floor($t); 
    $m = round(($t-$h)*60);
    if ($h<10) $h = "0".$h;
    if ($m<10) $m = "0".$m;
    return $h.$div.$m;
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

function schedule_log($message){
    if ($fh = @fopen("/var/log/emoncms/demandshaper.log","a")) {
        $now = microtime(true);
        $micro = sprintf("%03d",($now - ($now >> 0)) * 1000);
        $now = DateTime::createFromFormat('U', (int)$now); // Only use UTC for logs
        $now = $now->format("Y-m-d H:i:s").".$micro";
        @fwrite($fh,$now." | ".$message."\n");
        @fclose($fh);
    }
}
