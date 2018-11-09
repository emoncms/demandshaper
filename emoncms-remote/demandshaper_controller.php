<?php
  /*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
  */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function demandshaper_controller()
{
    global $mysqli,$redis,$session,$route;

    $result = false;
    
    $route->format = "json";

    // "https://api.carbonintensity.org.uk/intensity/$start/fw24h"
    if ($route->action == 'carbonintensity') $result = json_decode($redis->get("demandshaper:carbonintensity"));
    
    // "https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"
    else if ($route->action == 'octopus') $result = json_decode($redis->get("demandshaper:octopus"));

    return array('content'=>$result);
}
