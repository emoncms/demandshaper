# --------------------------------------------------------
# JAVASCRIPT VERSION OF SCHEDULE_TIMER 
# --------------------------------------------------------

The following code can be used in main.js instead of calling the backend API..

    schedule.runtime.periods = [];

    var d = new Date();
    d.setTime(forecast.start*1000);
    var start_hour = d.getHours()+(d.getMinutes()/60);

    d.setHours(0,0,0,0);
    var today = d.getTime();

    d.setDate(d.getDate()+1);
    var tomorrow = d.getTime();

    d.setDate(d.getDate()+1);
    var dayafter = d.getTime();

    d.setDate(d.getDate()-3);
    var yesterday = d.getTime();

    for (var z in schedule.settings.timer) {
        let timer = schedule.settings.timer[z]
        
        if (timer.start!=timer.end) {

            // 1. Yesterday
            d.setTime(yesterday);
            d = date_setHours(d,timer.start);
            let start = d.getTime()*0.001;
            if (timer.start>timer.end) d.setTime(today);      // if timer overlaps midnight end time is day+1 
            d = date_setHours(d,timer.end);
            let end = d.getTime()*0.001;
            // Only include if in the view
            if (end>=forecast.start) {
                schedule.runtime.periods.push({start:[start,timer.start],end:[end,timer.end]});
            }

            // 2. Today
            d.setTime(today);
            d = date_setHours(d,timer.start);
            start = d.getTime()*0.001;
            if (timer.start>timer.end) d.setTime(tomorrow);   // if timer overlaps midnight end time is day+1 
            d = date_setHours(d,timer.end);
            end = d.getTime()*0.001;
            // Only include if in the view
            schedule.runtime.periods.push({start:[start,timer.start],end:[end,timer.end]});
            
            // 3. Tomorrow
            d.setTime(tomorrow);
            d = date_setHours(d,timer.start);
            start = d.getTime()*0.001;
            if (timer.start>timer.end) d.setTime(dayafter);   // if timer overlaps midnight end time is day+1 
            d = date_setHours(d,timer.end);
            end = d.getTime()*0.001;
            // Only include if in the view
            if (start<=forecast.end) {
                schedule.runtime.periods.push({start:[start,timer.start],end:[end,timer.end]});
            }
        }
    }
