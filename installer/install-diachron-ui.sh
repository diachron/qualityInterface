#!/bin/bash

echo "Required to unzip virtuoso"
sudo apt-get install unzip

echo "Deploying Virtuoso Opensource to /usr/share/virtuoso-opensource-6.1.8..."
unzip ./virtuoso618.zip -d /usr/share/

echo "Deploying OntoWiki Web application to /home/londono/ontowiki-096..."
cp -a ../src/ontowiki-096/ /home/londono/

echo "Setting up symbolic link in Apache web folder.."
sudo ln -s /home/londono/ontowiki-096/ /var/www/ontowiki

sudo chmod -R 777 /home/londono/ontowiki-096/cache
sudo chmod -R 777 /home/londono/ontowiki-096/logs
sudo chmod -R 777 /home/londono/ontowiki-096/uploads
sudo chmod -R 777 /home/londono/ontowiki-096/extensions

echo "Enabling Apache's mod rewrite"
sudo a2enmod rewrite

echo "Please enable .htaccess, adding the following lines to /etc/apache2/sites-enabled/000-default"
echo "<Directory /var/www/ontowiki/>"
echo "     AllowOverride All"
echo "</Directory>"

echo "Installing Virtuoso as service"
sudo cp ./virtuoso /etc/init.d/
sudo chmod +x /etc/init.d/virtuoso
sudo update-rc.d virtuoso defaults
sudo service virtuoso start
sudo apache2ctl stop
sudo apache2ctl start
