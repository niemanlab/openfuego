# OpenFuego in a(n Amazon) Box

There is an AMI on Amazon Web Services that allows you to quickly deploy a cloud server to run OpenFuego. You can use [EC2 for Poets](http://ec2.forpoets.org/) as a guide. A full guide is forthcoming. Below I've documented what that box includes:

Software installed:

* ubuntu 12.04 LTS
* apache2
* php5 (with php5-curl)
* sendmail
* mysql-server (root password: 0p3n-fu3g0 - __change this immediately__)
* phpmyadmin (admin user password: 0p3n-fu3g0 - __change this immediately__)
* git

Commands run:

Followed Phelps' instructions [here](https://github.com/niemanlab/openfuego/issues/4#issuecomment-21755406)

Symlinked phpmyadmin (cmd: `ln -s /usr/share/phpmyadmin /var/www/phpmyadmin`)

Created folder /var/www/openfuego - fetched OpenFuego from Nieman (run `git pull origin master` in that folder to update to latest version)

Created index.php in /var/www that will quickly return some links as soon as they're pulled to test your implementation

What you need to do:

Log in to phpmyadmin and change the [mysql](http://www.cyberciti.biz/faq/mysql-change-root-password/) and [phpmyadmin](http://stackoverflow.com/questions/14703223/mysql-phpmyadmin-reset-root-password) passwords

Create database in phpmyadmin

Copy and edit config.php filled in for info

Run fetch.php
