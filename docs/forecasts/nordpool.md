# Nordpool

Forecast API:

    http://datafeed.expektra.se/datafeed.svc/spotprice?token=$token&bidding_area=$area&format=json&perspective=$currency&$time

Example response:

    {
      "id": "Electricity Spot Price",
      "unit": "EUR/MWh",
      "data": [
        {
          "utc": "2020-05-22T08:00:00Z",
          "value": "37.96"
        },
        {
          "utc": "2020-05-22T09:00:00Z",
          "value": "43.62"
        },
        {
          "utc": "2020-05-22T10:00:00Z",
          "value": "38.51"
        },
        {
          "utc": "2020-05-22T11:00:00Z",
          "value": "20.18"
        },
        {
          "utc": "2020-05-22T12:00:00Z",
          "value": "11.17"
        },
        {
          "utc": "2020-05-22T13:00:00Z",
          "value": "9.98"
        },
        {
          "utc": "2020-05-22T14:00:00Z",
          "value": "9.95"
        },
        {
          "utc": "2020-05-22T15:00:00Z",
          "value": "10.11"
        },
        {
          "utc": "2020-05-22T16:00:00Z",
          "value": "21.96"
        },
        {
          "utc": "2020-05-22T17:00:00Z",
          "value": "21.62"
        },
        {
          "utc": "2020-05-22T18:00:00Z",
          "value": "9.9"
        },
        {
          "utc": "2020-05-22T19:00:00Z",
          "value": "12.41"
        },
        {
          "utc": "2020-05-22T20:00:00Z",
          "value": "15.91"
        },
        {
          "utc": "2020-05-22T21:00:00Z",
          "value": "7.25"
        }
      ],
      "details": {
        "bidding_area": "FI",
        "source": "Nord Pool Spot",
        "provider": "Expektra",
        "condition": "This service may be used only by individuals and organizations in non-commercial purpose and it is not permitted to redistribute this data. Contact spotprice@expektra.com regarding other options. As of 2017-07-01 you will need an access token in order to access this service. Contact us on spotprice@expektra.com for more information about this."
      }
    }
