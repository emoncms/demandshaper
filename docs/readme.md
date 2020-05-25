# Requests and responses

### GET /demandshaper/get?device=mynode

Example response:

    {
        "schedule":
        {
            "settings":
            {
                "name": "mynode",
                "device": "mynode",
                "device_type": "smartplug",
                "ctrlmode": "smart",
                "signal": "carbonintensity",
                "period": 3,
                "end": 16,
                "interruptible": 0,
                "timer_start1": 0,
                "timer_stop1": 0,
                "timer_start2": 0,
                "timer_stop2": 0,
                "repeat":
                [
                    1,
                    1,
                    1,
                    1,
                    1,
                    1,
                    1
                ],
                "runonce": false,
                "flowT": 30,
                "openevsecontroltype": "time",
                "batterycapacity": 20,
                "chargerate": 3.8,
                "ovms_vehicleid": "",
                "ovms_carpass": "",
                "balpercentage": 0.9,
                "baltime": 45,
                "ev_soc": 0.2,
                "ev_target_soc": 0.8,
                "ip": "",
                "end_timestamp": 1590418800
            },
            "runtime":
            {
                "periods":
                [
                    {
                        "start":
                        [
                            1590408000,
                            13
                        ],
                        "end":
                        [
                            1590418800,
                            16
                        ]
                    }
                ],
                "timeleft": 10800,
                "last_update_from_device": 0
            }
        }
    }

### POST /demandshaper/submit

Request body:

    schedule=
    {
        "settings":
        {
            "name": "mynode",
            "device": "mynode",
            "device_type": "smartplug",
            "ctrlmode": "smart",
            "signal": "carbonintensity",
            "period": 4,
            "end": 16,
            "interruptible": 0,
            "timer_start1": 0,
            "timer_stop1": 0,
            "timer_start2": 0,
            "timer_stop2": 0,
            "repeat":
            [
                1,
                1,
                1,
                1,
                1,
                1,
                1
            ],
            "runonce": false,
            "flowT": 30,
            "openevsecontroltype": "time",
            "batterycapacity": 20,
            "chargerate": 3.8,
            "ovms_vehicleid": "",
            "ovms_carpass": "",
            "balpercentage": 0.9,
            "baltime": 45,
            "ev_soc": 0.2,
            "ev_target_soc": 0.8,
            "ip": ""
        },
        "runtime":
        {
            "periods":
            [
                {
                    "start": [1590404400,12],
                    "end": [1590418800,16]
                }
            ],
            "timeleft": 14400,
            "last_update_from_device": 0
        }
        
    }&save=1&apikey=90b4762db2dfd7e1a169f6720f6e4596

Response:

    {
        "schedule":
        {
            "settings":
            {
                "name": "mynode",
                "device": "mynode",
                "device_type": "smartplug",
                "ctrlmode": "smart",
                "signal": "carbonintensity",
                "period": 4,
                "end": 16,
                "interruptible": 0,
                "timer_start1": 0,
                "timer_stop1": 0,
                "timer_start2": 0,
                "timer_stop2": 0,
                "repeat":
                [
                    1,
                    1,
                    1,
                    1,
                    1,
                    1,
                    1
                ],
                "runonce": false,
                "flowT": 30,
                "openevsecontroltype": "time",
                "batterycapacity": 20,
                "chargerate": 3.8,
                "ovms_vehicleid": "",
                "ovms_carpass": "",
                "balpercentage": 0.9,
                "baltime": 45,
                "ev_soc": 0.2,
                "ev_target_soc": 0.8,
                "ip": "",
                "end_timestamp": 1590418800
            },
            "runtime":
            {
                "periods":
                [
                    {
                        "start": [1590404400,12],
                        "end": [1590418800,16]
                    }
                ],
                "timeleft": 14400,
                "last_update_from_device": 0
            }
        }
    }
    
    
# -------------------------------------------------------------

Exampled of smartplug schedule object:

    {
        "schedule":
        {
            "settings":
            {
                "name": "mynode",
                "device": "mynode",
                "device_type": "smartplug",
                "forecast":[
                    {
                        "name":"octopusagile", 
                        "gsp_id":"D", 
                        "weight":1.0
                    },
                    {
                        "name":"solcast", 
                        "siteid":"8ffd-005c-5686-8116", 
                        "api_key":"pBQwdyxbLutCFemv2My1SXdW6aEIAd2K", 
                        "weight":-5.0
                    }
                ],
                "ctrlmode": "block",
                "period": 10800,
                "end": 1590418800,
                "ip": "",
            },
            "runtime":
            {
                "periods":
                [
                    {
                        "start": [1590404400,12],
                        "end": [1590418800,16]
                    }
                ],
                "timeleft": 14400,
                "last_update_from_device": 0
            }
        }
    }
