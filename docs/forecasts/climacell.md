# ClimaCell Solar Forecast

Forecast API:

    https://api.climacell.co/v3/weather/forecast/hourly?lat=&lon=&unit_system=si&start_time=&end_time=&fields=surface_shortwave_radiation&apikey=

Example response:

    [
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":155,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T17:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":88,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T18:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":86,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T19:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":79,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T20:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":72,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T21:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":67,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T22:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":90,"units":"w/sqm"},"observation_time":{"value":"2020-05-23T23:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":83,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T00:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":76,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T01:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":71,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T02:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":66,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T03:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":62,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T04:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":58,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T05:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":55,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T06:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":52,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T07:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":50,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T08:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":48,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T09:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":46,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T10:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":49,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T11:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":49,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T12:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":48,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T13:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":46,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T14:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":45,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T15:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":48,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T16:00:00.000Z"}},
        {"lon":-7.630868,"lat":56.782122,"surface_shortwave_radiation":{"value":48,"units":"w/sqm"},"observation_time":{"value":"2020-05-24T17:00:00.000Z"}}
    ]

Parsed profile:

    [
        [1590255000000,155],
        [1590256800000,88],
        [1590258600000,88],
        [1590260400000,86],
        [1590262200000,86],
        [1590264000000,79],
        [1590265800000,79],
        [1590267600000,72],
        [1590269400000,72],
        [1590271200000,67],
        [1590273000000,67],
        [1590274800000,90],
        [1590276600000,90],
        [1590278400000,83],
        [1590280200000,83],
        [1590282000000,76],
        [1590283800000,76],
        [1590285600000,71],
        [1590287400000,71],
        [1590289200000,66],
        [1590291000000,66],
        [1590292800000,62],
        [1590294600000,62],
        [1590296400000,58],
        [1590298200000,58],
        [1590300000000,55],
        [1590301800000,55],
        [1590303600000,52],
        [1590305400000,52],
        [1590307200000,50],
        [1590309000000,50],
        [1590310800000,48],
        [1590312600000,48],
        [1590314400000,46],
        [1590316200000,46],
        [1590318000000,49],
        [1590319800000,49],
        [1590321600000,49],
        [1590323400000,49],
        [1590325200000,48],
        [1590327000000,48],
        [1590328800000,46],
        [1590330600000,46],
        [1590332400000,45],
        [1590334200000,45],
        [1590336000000,48],
        [1590337800000,48],
        [1590339600000,48]
    ]

