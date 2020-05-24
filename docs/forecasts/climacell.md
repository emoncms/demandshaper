# ClimaCell Solar Forecast

Forecast API:

    https://api.climacell.co/v3/weather/forecast/hourly?lat=&lon=&unit_system=si&start_time=&end_time=&fields=surface_shortwave_radiation&apikey=

Example response:

    [
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":60,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T20:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":51,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T21:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":44,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T22:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":38,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T23:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":34,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T00:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":31,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T01:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":28,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T02:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":25,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T03:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":23,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T04:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":22,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T05:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":23,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T06:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":26,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T07:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":29,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T08:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":36,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T09:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":41,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T10:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":40,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T11:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":39,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T12:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":37,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T13:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":36,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T14:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":34,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T15:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":33,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T16:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":32,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T17:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":30,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T18:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":30,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T19:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":30,"units":"w/sqm"},"observation_time":{"value":"2020-05-25T20:00:00.000Z"}}
    ]

Parsed profile:

    {
        "start": 1590352200,
        "end": 1590438600,
        "interval": 1800,
        "profile":
        [
            60,
            51,
            51,
            44,
            44,
            38,
            38,
            34,
            34,
            31,
            31,
            28,
            28,
            25,
            25,
            23,
            23,
            22,
            22,
            23,
            23,
            26,
            26,
            29,
            29,
            36,
            36,
            41,
            41,
            40,
            40,
            39,
            39,
            37,
            37,
            36,
            36,
            34,
            34,
            33,
            33,
            32,
            32,
            30,
            30,
            30,
            30,
            30
        ],
        "optimise": 1
    }
