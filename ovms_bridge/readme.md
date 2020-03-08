# OVMS Bridge

Subscribes to OVMS SOC topic on OVMS Server v3 and forwards this onto local emonPi MQTT server.

## Configure:

Set remote MQTT broker settings in ovms_bridge.py:

    # Connect to OVMS MQTT Server
    try:
        mqtt_ovms.tls_set(ca_certs="/usr/share/ca-certificates/mozilla/DST_Root_CA_X3.crt")
        mqtt_ovms.username_pw_set("username", "pass")
        mqtt_ovms.connect("host", 8883, 60)

## Install:

    sudo ln -s /opt/emoncms/modules/demandshaper/ovms_bridge/ovms_bridge.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable ovms_bridge.service
    sudo systemctl start ovms_bridge
