# Install demandshaper module within an existing emoncms installation

The following installation instructions uses the new [EmonScripts](https://github.com/openenergymonitor/EmonScripts) emoncms symlinked modules directory location: **/opt/emoncms/modules**. 

If you do not already have this directory, start by creating the directory:

    sudo mkdir /opt/emoncms
    sudo chown pi:pi /opt/emoncms
    mkdir /opt/emoncms/modules

Download or git clone the demandshaper repository to your home folder:

    cd /opt/emoncms/modules
    git clone https://github.com/emoncms/demandshaper.git
    
 Run demandshaper module installation script:
 
    cd demandshaper
    ./install.sh

Link the 'demandshaper-module' into the emoncms Modules folder:

    ln -s /opt/emoncms/modules/demandshaper/demandshaper-module /var/www/emoncms/Modules/demandshaper

Update emoncms database

    Setup > Administration > Update database > Update & Check

**Optional:** Enable the periodic UDP broadcast script on the emonbase/emonpi:

    crontab -e
    * * * * * php /opt/openenergymonitor/emonpi/UDPBroadcast/broadcast.php 2>&1
