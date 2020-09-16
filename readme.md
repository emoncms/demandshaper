# Demand Shaper

Appliance, Smartplug, [WiFi Relay](https://shop.openenergymonitor.com/wifi-mqtt-relay-thermostat/), [EmonEVSE / OpenEVSE EV Charging Station](https://guide.openenergymonitor.org/integrations/ev-charging/), [HeatpumpMonitor](https://heatpumpmonitor.org/) demand shaper: Find the best time to run a household load.

The demand shaper module uses a day ahead power availability forecast and user set schedules to determine the best time to run household loads. An example could be charging an electric car, the user enters a desired completion time and charge duration, the demand shaper module then works out the best time to charge the car, generally there will be higher power availability overnight and during sunny midday hours. The demand shaper attempts to avoid running appliances at peak times while ensuring that the appliance has completed the required run period.

Developed as part of the CydYnni EnergyLocal project, see:
[https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post](https://community.openenergymonitor.org/t/cydynni-energylocal-community-hydro-smart-grid-blog-post)

![demandshaper.png](images/demandshaper.png?v=1)

## Supported forecasts

- [Octopus Agile](https://octopus.energy/agile)
- [CarbonIntensity UK Grid](https://www.carbonintensity.org.uk/)
- [Energy Local day ahead forecasts](https://energylocal.org.uk)
- [Nordpool](https://www.nordpoolgroup.com/)
- [Solcast Solar Forecast](https://solcast.com)
- [ClimaCell Solar Forecast](https://www.climacell.co)

**New in v2:** Multiple forecasts can now be combined such as the Solcast Solar Forecast with the Octopus Agile forecast to allow optimisation of both on-site solar and import from Octopus Agile overnight.

## Requirements

- Emoncms Core
- Emoncms Device module
- MQTT Mosquitto broker and MQTT emoncms setup as per emonSD image
- Emoncms dependencies: Apache2, MariaDB, Redis etc.

## Install

The DemandShaper module is pre-installed on the emonSD image that is shipped with OpenEnergyMonitor EmonPi and EmonBase devices. If you dont have a EmonPi or EmonBase but do have a spare RaspberryPi, the easiest way to use the DemandShaper module is to download the latest emonSD SD card image so that you have the full Emoncms stack. Alternatively it is possible to build the Emoncms stack from scratch (useful for non-Pi systems e.g a cloud VM) using EmonScripts, or if you already have an manual Emoncms installation see steps for manual installation below.

**Installation Options:**

1. **Use pre-built emonSD SD card image for a RaspberryPi**<br>
Download the latest image here:<br>
https://github.com/openenergymonitor/emonpi/wiki/emonSD-pre-built-SD-card-Download-&-Change-Log

2. **Install full emoncms system including demandshaper module using EmonScripts**<br>
Just run our automated emoncms installation script on a target system of choice, see:<br>
[https://github.com/openenergymonitor/EmonScripts](https://github.com/openenergymonitor/EmonScripts)

3. [Install demandshaper module within an existing emoncms installation](docs/manual-install.md)

## User Guides

- [Sonoff S20 Smart Plug](https://guide.openenergymonitor.org/integrations/demandshaper-sonoff/)
- [OpenEVSE / EmonEVSE electric car smart charging](https://guide.openenergymonitor.org/integrations/demandshaper-openevse/)

The module contains custom interfaces for applications such as EV charging where you can drag drop the battery level state of charge to the desired target, the module then calculates the required run time based on the battery size and charger charge rate.

## Developer Guide

1. [Architecture](docs/architecture.md)
2. [Add a new forecast](docs/add-a-new-forecast.md)
3. [Parsing forecasts](docs/forecasts/intro.md)
4. [Add a new device](docs/add-a-new-device.md)
5. [The schedule object](docs/schedule-object.md)
6. [Testing using the CLI scripts](docs/using-cli-tools.md)

**Parsed Forecast Examples**

- [Octopus Agile](docs/forecasts/octopusagile)
- [CarbonIntensity UK Grid](docs/forecasts/carbonintensity)
- [Energy Local day ahead forecasts](docs/forecasts/energylocal)
- [Nordpool](docs/forecasts/nordpool)
- [ClimaCell Solar Forecast](docs/forecasts/climacell)

## Security

- The standard configuration on emonSD and EmonScripts based systems include basic authentication for MQTT access. 
- Devices running EmonESP such as the SmartPlug can be configured with an admin username and password to limit access.
