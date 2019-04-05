#!/bin/bash
echo "DemandShaper module installation script"
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

service=demandshaper

if [ ! -f /lib/systemd/system/$service.service ]; then
    echo "- installing $service.service"
    sudo cp $DIR/$service.service /lib/systemd/system
    sudo sed -i "s~ExecStart=.*~ExecStart=/usr/bin/php $DIR/run.php~" /lib/systemd/system/$service.service
    sudo systemctl enable $service.service
    sudo systemctl start $service.service
else
    echo "- reinstalling $service.service"
    sudo rm /lib/systemd/system/$service.service
    sudo cp $DIR/$service.service /lib/systemd/system
    sudo sed -i "s~ExecStart=.*~ExecStart=/usr/bin/php $DIR/run.php~" /lib/systemd/system/$service.service
    sudo systemctl daemon-reload
    sudo systemctl restart $service.service
fi

state=$(systemctl show $service | grep ActiveState)
echo "- Service $state"
