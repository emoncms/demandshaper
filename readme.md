# Demand Shaper

Appliance, Smartplug, OpenEVSE demand shaper: Find the best time to run household load.

The demand shaper module uses a day ahead power availability forecast and user set schedules to determine the best time to run household loads. An example could be charging an electric car, the user enters a desired completion time and charge duration, the demand shaper module then works out the best time to charge the car, generally there will be higher power availability overnight and during sunny midday hours. The demand shaper attempts to avoid running appliances at peak times while ensuring that the appliance has completed the required run period.

Developed as part of the CydYnni EnergyLocal project, see:
[https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post](https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post)

![demandshaper.png](images/demandshaper.png)

**9th November 2018:** The demand shaper module now supports the use of the UK grid carbon intensity and Octopus Agile forecasts.

## Requirements

- Emoncms
- Emoncms Device module
- MQTT Mosquitto broker and MQTT emoncms setup as per emonSD image
- Redis

## Installation 

Download or git clone the demandshaper repository to your home folder:

    cd
    git clone https://github.com/emoncms/demandshaper.git
    
Link the 'demandshaper-module' into the emoncms Modules folder:

    ln -s /home/pi/demandshaper/demandshaper-module /var/www/emoncms/Modules/demandshaper 
    
## Run

The demand shaper background process is called run.php. It can be ran manually with:

    php run.php

Or from cron with:

    crontab -e
    * * * * * php /home/pi/demandshaper/run.php 2>&1
    
The demand shaper process publishes the device state to an MQTT topic of the form:

    emon/devicename/state

## Remote Cache

The remote cache currently run's on emoncms.org to reduce the potential API load on the Octopus and CarbonIntensity servers. The cache provides the following routes:

    https://emoncms.org/demandshaper/carbonintensity
    https://emoncms.org/demandshaper/octopus

To install and use the cache on your own server. Symlink emoncms-remote module to Modules folder:

    ln -s /home/username/demandshaper/emoncms-remote /var/www/emoncms/Modules/demandshaper


Add the cron entry:

    0 * * * * php /home/username/demandshaper/emoncms_remote_cache.php >> /var/log/demandshaper-cache.log

## Using the Demand Shaper module with a SonOff S20 smart plug

**Preperation**

1. Install the EmonESP (control\_merge branch) firmware on a Sonoff S20 smartplug. See guide here:<br>[https://github.com/openenergymonitor/EmonESP/blob/control_merge/sonoffS20.md](https://github.com/openenergymonitor/EmonESP/blob/control_merge/sonoffS20.md)

2. Enable the UDP broadcast script on the emonbase/emonpi:

<pre>
    crontab -e
    * * * * * php /home/pi/emonpi/UDPBroadcast/broadcast.php 2>&1
</pre>

3. Install the demand shaper module as above and make sure that you have the latest emoncms master branch and latest emoncms device module installed.

**User guide**

1. The Sonoff S20 smartplug creates a WIFI access point, connect to the access point and enter home WIFI network. That is all the configuration required.

<table><tr><td>
![emonesp1.png](images/emonesp1.png)
</td><td>
![emonesp2.png](images/emonesp2.png)
</td></tr></table>

2. Connect back to your home WIFI network. Login to emoncms and navigate to inputs. Refresh the page until a popup appears asking to connect:

![emoncms_allow.png](images/emoncms_allow.png)

3. After clicking allow the smart plug will then appear in the inputs list with a small icon identifying it as a schedulable device: 

![schedulericon.png](images/schedulericon.png)

4. Click on the clock icon to load the demandshaper module, where the smart plug can be scheduled:

![demandshaper.png](images/demandshaper.png)

5. Wait for the plug to turn on! :)

![smartplug_on.png](images/smartplug_on.png)
