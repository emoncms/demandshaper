#!/bin/bash
echo "DemandShaper module installation script"
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# ---------------------------------------------------------
# Install service
# ---------------------------------------------------------
service=demandshaper

if [ -f /lib/systemd/system/$service.service ]; then
    echo "- reinstalling $service.service"
    sudo systemctl stop $service.service
    sudo systemctl disable $service.service
    sudo rm /lib/systemd/system/$service.service
else
    echo "- installing $service.service"
fi

sudo cp $DIR/$service.service /lib/systemd/system
# Set ExecStart path to point to installed script location
sudo sed -i "s~ExecStart=.*~ExecStart=/usr/bin/php $DIR/demandshaper_run.php~" /lib/systemd/system/$service.service

# echo "installing demandshaper drop-in User=$user"
# sudo mkdir /lib/systemd/system/demandshaper.service.d
# echo $'[Service]\nUser='$user > demandshaper.conf
# sudo mv demandshaper.conf /lib/systemd/system/demandshaper.service.d/demandshaper.conf

sudo systemctl enable $service.service
sudo systemctl restart $service.service

state=$(systemctl show $service | grep ActiveState)
echo "- Service $state"

# ---------------------------------------------------------
