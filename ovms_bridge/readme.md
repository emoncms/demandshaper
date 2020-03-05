# OVMS Bridge

Subscribes to OVMS SOC topic on OVMS Server v3 and forwards this onto local emonPi MQTT server.

## Install:

    sudo ln -s /opt/emoncms/modules/demandshaper/ovms_bridge/ovms_bridge.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable ovms_bridge.service
    sudo systemctl start ovms_bridge
