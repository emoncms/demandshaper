# Developer Guide: Schedule Object Example

The following gives an example of a schedule object for the OpenEvse. 

The settings section is saved to disk as they should only change during configuration, whilst the runtime properties are always changing and so are saved to redis only.

**GET /demandshaper/get?device=openevse**

    {
        "schedule":
        {
            "settings":
            {
                "device": "openevse",
                "device_type": "openevse",
                "device_name": "Car",
                "ctrlmode": "smart",
                "period": 1.5,
                "end": 8,
                "end_timestamp": 1600326000,
                "interruptible": 0,
                "runone": 0,
                "forecast_config":
                [
                    {
                        "name": "octopusagile",
                        "weight": 1,
                        "gsp_id": "D"
                    }
                ],
                "forecast_units": "pkwh",
                "ip": "",
                "soc_source": "ovms",
                "battery_capacity": 19,
                "charge_rate": 3.8,
                "target_soc": 1,
                "current_soc": 0.7,
                "balpercentage": 0.9,
                "baltime": 1,
                "car_economy": 4,
                "charge_energy": 5.7,
                "charge_distance": 22.8,
                "distance_units": "miles",
                "ovms_vehicleid": "",
                "ovms_carpass": "skyeleaf",
                "divert_mode": 0
            },
            "runtime":
            {
                "started": false,
                "periods":
                [
                    {
                        "start": [1600309801,3.5],
                        "end": [1600315200,5]
                    }
                ],
                "timeleft": 5400
            }
        }
    }
