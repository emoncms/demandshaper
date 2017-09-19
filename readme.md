# Demand Shaper

Appliance, Smartplug, OpenEVSE demand shaper: Find the best time to run household load.

The demand shaper module uses a day ahead power availability forecast and user set schedules to determine the best time to run household loads. An example could be charging an electric car, the user enters a desired completion time and charge duration, the demand shaper module then works out the best time to charge the car, generally there will be higher power availability overnight and during sunny midday hours. The demand shaper attempts to avoid running appliances at peak times while ensuring that the appliance has completed the required run period.

Developed as part of the CydYnni EnergyLocal project, see:
[https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post](https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post)

![demandshaper.png](images/demandshaper.png)


## Requirements

- Emoncms device-support branch
- Device module device-integration branch
- MQTT Mosquitto broker and MQTT emoncms setup as per emonSD image

## Installation 

Download or git clone the demandscheduler repository to your home folder:

    cd
    git clone https://github.com/emoncms/demandscheduler.git
    
Link the 'demandscheduler-module' into the emoncms Modules folder:

    ln -s /home/pi/demandscheduler/demandscheduler-module /var/www/emoncms/Modules/demandscheduler
    
Copy smartplug device template to device module:

    cp demandshaper/demandshaper-module/smartplug.json /var/www/emoncms/Modules/device/data/smartplug.json


