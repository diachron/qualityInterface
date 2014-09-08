DIACHRON WEB UI - INSTALLATION

- DEBIAN/UBUNTU

Please make sure that Apache and PHP 5 have been installed and are properly setup. 
To install the whole LAMP stack (Apache 2, PHP 5 and MySQL 5.0), run:

	sudo apt-get update
	sudo apt-get install lamp-server^

Alternatively, Apache 2 and PHP 5 can also be installed separately:

	sudo apt-get update
	sudo apt-get install apache2
	sudo apt-get install libapache2-mod-php5

Additional information is provided at:
https://help.ubuntu.com/community/ApacheMySQLPHP

The installation process will also install the unzip package automatically.

To run the installation process, please run the install-diachron-ui script as sudo, from the 
installer directory (where the script is located). That is:

	sudo chmod +x install-diachron-ui.sh
	sudo ./install-diachron-ui.sh

Virtuoso Opensource will be configured as a service as part of the installation process, 
to manually start/stop the service please run:

	sudo service virtuoso start
	sudo service virtuoso stop