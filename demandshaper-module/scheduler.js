// -------------------------------------------------------------------------------------------------------
// SMART SCHEDULE
// -------------------------------------------------------------------------------------------------------
function schedule_smart(forecast,timeleft,end,interruptible,resolution)
{
    var MIN = 0
    var MAX = 1
    var forecast_length = 24;
    
    var resolution_h = resolution/3600
    var divisions = Math.round(forecast_length*3600/resolution)
    
    // period is in hours
    var period = timeleft / 3600
    if (period<0) period = 0
    
    // Start time
    var date = new Date()
    var now = Math.round(date.getTime()*0.001)
    var timestamp = Math.floor(now/resolution)*resolution
    date.setTime(timestamp*1000)
            
    var h = date.getHours()
    var m = date.getMinutes()/60
    var start_hour = h + m
    
    // End time
    end = Math.floor(end / resolution_h) * resolution_h
    
    var midnight = timestamp - (start_hour*3600)
    var end_timestamp = midnight + end*3600
    if (end_timestamp<now) end_timestamp+=3600*forecast_length
    
    var profile = forecast.profile

    // --------------------------------------------------------------------------------
    // Upsample profile
    // -------------------------------------------------------------------------------
    let upsampled = [];            
    
    let profile_start = profile[0][0]*0.001;
    let profile_end = profile[profile.length-1][0]*0.001;

    for (let timestamp=profile_start; timestamp<profile_end; timestamp+=resolution) {
        let i = Math.floor((timestamp - profile_start)/forecast.resolution);
        if (profile[i]!=undefined) {
            let value = profile[i][1]
            
            let date = new Date(timestamp*1000);
            let h = date.getHours();
            let m = date.getMinutes()/60;
            let hour = h + m;
            upsampled.push([timestamp*1000,value,hour]);
        }
    }            
    profile = upsampled
    // --------------------------------------------------------------------------------
    
    // No half hours allocated yet
    for (let td=0; td<profile.length; td++) {
        profile[td][3] = 0
    }

    if (!interruptible) 
    {
        // We are trying to find the start time that results in the maximum sum of the available power
        // max is used to find the point in the forecast that results in the maximum sum..
        let threshold = 0

        // When max available power is found, start_time is set to this point
        let pos = 0

        // ---------------------------------------------------------------------------------
        // Method 1: move fixed period of demand over probability function to find best time
        // ---------------------------------------------------------------------------------
        
        // For each time division in profile
        for (let td=0; td<profile.length; td++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             let sum = 0
             let valid_block = 1
             for (let i=0; i<period*(divisions/forecast_length); i++) {
                 
                 if (profile[td+i]!=undefined) {
                     if (profile[td+i][0]*0.001>=end_timestamp) valid_block = 0
                     sum += profile[td+i][1]
                 } else {
                     valid_block = 0
                 }
             }
             
             if (td==0) threshold = sum
             
             // Determine the start_time which gives the maximum sum of available power
             if (valid_block) {
                 if ((forecast.optimise==MIN && sum<threshold) || (forecast.optimise==MAX && sum>threshold)) {
                     threshold = sum
                     pos = td
                 }
             }
        }
        
        let start_hour = 0
        let tstart = 0
        if (profile[pos]!=undefined) {
            start_hour = profile[pos][2]
            tstart = profile[pos][0]*0.001
        }
        let end_hour = start_hour
        let tend = tstart
        
        for (let i=0; i<period*(divisions/forecast_length); i++) {
            if (profile[pos+i]!=undefined) {
                profile[pos+i][3] = 1
                end_hour+=resolution/3600
                tend+=resolution
                if (end_hour>=24) end_hour -= 24
                // dont allow to run past end time
                if (tend==end_timestamp) break
            }
        }
        
        let periods = []
        if (period>0) {
            periods.push({start:[tstart,start_hour], end:[tend,end_hour]})
        }
        return periods

    } else {
        // ---------------------------------------------------------------------------------
        // Method 2: Fill into times of most available power first
        // ---------------------------------------------------------------------------------

        // For each hour of demand
        for (let p=0; p<period*(divisions/forecast_length); p++) {

            if (forecast.optimise==MIN) threshold = forecast.max; else threshold = forecast.min;
            let pos = -1
            // for each hour in probability profile
            for (let td=0; td<profile.length; td++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && val>max
                let val = profile[td][1]
                
                if (profile[td][0]*0.001<end_timestamp && !profile[td][3]) {
                    if ((forecast.optimise==MIN && val<=threshold) || (forecast.optimise==MAX && val>=threshold)) {
                        threshold = val
                        pos = td
                    }
                }
            }
            
            // Allocate hour with maximum amount of available power
            if (pos!=-1) profile[pos][3] = 1
        }
                
        let periods = []
        
        let start = null
        let tstart = null
        let tend = null
        
        let i = 0
        let last = 0
        
        for (var td=0; td<profile.length; td++) {
            let hour = profile[td][2]
            let timestamp = profile[td][0]*0.001
            let val = profile[td][3]
        
            if (i==0) {
                if (val) {
                    start = hour
                    tstart = timestamp
                }
                last = val
            }
            
            if (last==0 && val==1) {
                start = hour
                tstart = timestamp
            }
            
            if (last==1 && val==0) {
                end = hour*1
                tend = timestamp
                periods.push({start:[tstart,start], end:[tend,end]})
            }
            
            last = val
            i++
        }
        
        if (last==1) {
            end = hour+resolution/3600
            tend = timestamp + resolution
            periods.push({start:[tstart,start], end:[tend,end]})
        }
        
        return periods
    }
}

// -------------------------------------------------------------------------------------------------------
// BASIC TIMER SCHEDULE
// -------------------------------------------------------------------------------------------------------
function schedule_timer(forecast,start1,stop1,start2,stop2,resolution) {

    /*
    let h = Math.floor(start1);
    let m = Math.round((start1 - h) * 60);
    date.setHours(h,m,0,0);
    let tstart1 = date.getTime()*0.001;
    ...
    */
    
    tstart1 = 0; tstop1 = 0;
    tstart2 = 0; tstop2 = 0;
    
    let profile_start = forecast.profile[0][0]*0.001;
    let profile_end = forecast.profile[forecast.profile.length-1][0]*0.001;

    let date = new Date();
    for (let td=profile_start; td<profile_end; td+=resolution) {
        date.setTime(td*1000);
        let hour = date.getHours()+(date.getMinutes()/60)
        if (hour==start1) tstart1 = td
        if (hour==stop1) tstop1 = td
        if (hour==start2) tstart2 = td
        if (hour==stop2) tstop2 = td
    }

    if (tstart1>tstop1) tstart1 -= 3600*24;
    if (tstart2>tstop2) tstart2 -= 3600*24;
               
    var periods = []
    periods.push({start:[tstart1,start1], end:[tstop1,stop1]})
    periods.push({start:[tstart2,start2], end:[tstop2,stop2]})
    return periods
}
