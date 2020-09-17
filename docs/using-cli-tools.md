# Developer Guide: Testing using the CLI scripts

Navigate to the CLI directory:

    cd /opt/emoncms/modules/demandshaper/cli


**cli_test_forecast.php**

A useful script for checking that a forecast script is loading the forecast correctly. Set the forecast to load manually in the script before running. The default is the carbon intensity forecast.

    php cli_test_forecast.php
    
Prints out forecast as date time and values:

    ----------------------------------
    Forecast: carbonintensity
    ----------------------------------
    2020-09-16 15:30	218
    2020-09-16 16:00	224
    2020-09-16 16:30	229
    2020-09-16 17:00	227
    ...
    
---
    
**cli_schedule.php**

Example of scheduling using a forecast. Set the schedule and select the forecast in the script before running.

    php cli_schedule.php
    
Prints out time to calculate the schedule, the best schedule timing (start and end times) and draws a star for each half hour that the schedule has selected on the printout.

    104 us
    {"start":[1600306200,2.5],"end":[1600317000,5.5]}
    Total run time: 10800
    MATCH

    2020-09-16 15:30	0	10.164	
    2020-09-16 16:00	1	25.2	
    2020-09-16 16:30	2	27.1425
    ...
    2020-09-17 02:00	21	8.967	
    2020-09-17 02:30	22	8.6835	*
    2020-09-17 03:00	23	8.547 	*
    2020-09-17 03:30	24	8.316	  *
    2020-09-17 04:00	25	8.316  	*
    2020-09-17 04:30	26	8.316	  *
    2020-09-17 05:00	27	8.3895	*
    2020-09-17 05:30	28	9.24	
    2020-09-17 06:00	29	10.164	

---	

**cli_combine_forecasts.php**

Example of combining two forecasts together e.g Agile and Solar. Set forecasts in the script.

    php cli_combine_forecasts.php

Result:
    
    ----------------------------------
    Combine Forecasts
    ----------------------------------
    Date:			Agile	Solar	Combined
    2020-09-16 15:30	10.164	0.6153	7.0875
    2020-09-16 16:00	25.2	0.4865	22.7675
    2020-09-16 16:30	27.1425	0.4433	24.926
    2020-09-16 17:00	26.145	0.3122	24.584
    2020-09-16 17:30	28.434	0.2287	27.2905
    2020-09-16 18:00	28.434	0.1366	27.751


**cli_combine_forecasts_schedule.php**

Example of combining two forecasts together and then running the scheduler on the result.

    php cli_combine_forecasts_schedule.php
    
Result: As last but with stars next to schedule half hours.

**cli_combine_forecasts_schedule_config.php**

Example of using the config format to define the forecasts that are to be combined.

    php cli_combine_forecasts_schedule_config.php
    
Result: As last but with stars next to schedule half hours. Combined forecast shown only.
    
Config format defined in script:

    [
        {
            "name": "octopusagile",
            "gsp_id": "D",
            "weight": 1
        },
        {
            "name": "solcast",
            "weight": -5,
            "siteid": "'.$solcast_siteid.'",
            "api_key": "'.$solcast_apikey.'"
        }
    ]
    

