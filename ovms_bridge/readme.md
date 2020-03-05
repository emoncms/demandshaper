# Systemd unit file for OVMS bridge

# INSTALL:

    sudo ln -s /opt/emoncms/modules/demandshaper/ovms_bridge/ovms_bridge.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable ovms_bridge.service
    sudo systemctl start ovms_bridge
