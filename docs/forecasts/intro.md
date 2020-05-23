# Forecast parsers

The goal of the forecast parsers is to covert different forecasts into a common format that can then be used by the scheduling algorithms.

In order to make it possible to combine multiple forecasts e.g Octopus Agile and a home solar forecast from Solcast, parsed forecasts need to align on the same start, end time and interval.

Some forecasts are provided at half hourly resolution, others may be hourly or every 3 hours. In order to make it possible to combine forcasts whos original intervals are different, the parsers need to downsample or upsample forecasts to a common interval;

### Caching

In order to reduce API calls to forcast servers and to speed up schedule recalculation, forecasts should be cached using redis with an expiry time set to that updated forecasts are automatically loaded.


