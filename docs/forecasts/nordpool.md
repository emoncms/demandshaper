# Nordpool

Forecast API:

    http://datafeed.expektra.se/datafeed.svc/spotprice?token=$token&bidding_area=$area&format=json&perspective=$currency&$time

Example response:

    {
        "id": "Electricity Spot Price",
        "unit": "DKK/MWh",
        "data":
        [
            {"utc":"2020-05-24T21:00:00Z","value":"38.04"},
            {"utc":"2020-05-24T22:00:00Z","value":"30.35"},
            {"utc":"2020-05-24T23:00:00Z","value":"29.16"},
            {"utc":"2020-05-25T00:00:00Z","value":"29.91"},
            {"utc":"2020-05-25T01:00:00Z","value":"50.64"},
            {"utc":"2020-05-25T02:00:00Z","value":"57.65"},
            {"utc":"2020-05-25T03:00:00Z","value":"74.51"},
            {"utc":"2020-05-25T04:00:00Z","value":"118.96"},
            {"utc":"2020-05-25T05:00:00Z","value":"157.29"},
            {"utc":"2020-05-25T06:00:00Z","value":"164.53"},
            {"utc":"2020-05-25T07:00:00Z","value":"156.03"},
            {"utc":"2020-05-25T08:00:00Z","value":"149.16"},
            {"utc":"2020-05-25T09:00:00Z","value":"156.17"},
            {"utc":"2020-05-25T10:00:00Z","value":"130.52"},
            {"utc":"2020-05-25T11:00:00Z","value":"115.38"},
            {"utc":"2020-05-25T12:00:00Z","value":"107.25"},
            {"utc":"2020-05-25T13:00:00Z","value":"104.41"},
            {"utc":"2020-05-25T14:00:00Z","value":"117.47"},
            {"utc":"2020-05-25T15:00:00Z","value":"140.96"},
            {"utc":"2020-05-25T16:00:00Z","value":"175.49"},
            {"utc":"2020-05-25T17:00:00Z","value":"194.51"},
            {"utc":"2020-05-25T18:00:00Z","value":"285.8"},
            {"utc":"2020-05-25T19:00:00Z","value":"256.19"},
            {"utc":"2020-05-25T20:00:00Z","value":"195.78"},
            {"utc":"2020-05-25T21:00:00Z","value":"172.96"}
        ],
        "details":
        {
            "bidding_area": "DK1",
            "source": "Nord Pool Spot",
            "provider": "Expektra",
            "condition": "This service may be used only by individuals and organizations in non-commercial purpose and it is not permitted to redistribute this data. Contact spotprice@expektra.com regarding other options. As of 2017-07-01 you will need an access token in order to access this service. Contact us on spotprice@expektra.com for more information about this."
        }
    }
    
Parsed response:

    {
        "start": 1590352200,
        "end": 1590438600,
        "interval": 1800,
        "profile":
        [
            24.473,
            4.755,
            4.755,
            3.794,
            3.794,
            3.645,
            3.645,
            3.739,
            3.739,
            6.33,
            6.33,
            7.206,
            7.206,
            9.314,
            9.314,
            14.87,
            14.87,
            19.661,
            19.661,
            20.566,
            20.566,
            19.504,
            19.504,
            18.645,
            18.645,
            19.521,
            19.521,
            16.315,
            16.315,
            14.423,
            14.423,
            13.406,
            13.406,
            13.051,
            13.051,
            14.684,
            14.684,
            17.62,
            17.62,
            21.936,
            21.936,
            24.314,
            24.314,
            35.725,
            35.725,
            32.024,
            32.024,
            24.473
        ],
        "optimise": 0
    }
