WerewolfServer
==============

Game available on: https://symfony-0tdz.frb.io/werewolf (set API_URL in Options to https://symfony-0tdz.frb.io/) <br />

# How to (Short Version)

1) Clone/download project: `git clone https://github.com/PagnoDunadan/WerewolfServer.git` <br />
2) Rename parameters.yml.dist into parameters.yml in /app/config and insert your configuration <br />
3) Create database: `$php bin/console doctrine:database:create` <br />
   Preview query: `$php bin/console doctrine:schema:update --dump-sql` <br />
   Execute: `$php bin/console doctrine:schema:update --force` <br />
4) Check your IP: `$ifconfig` (e.g. inet addr:192.168.1.4) <br />
   Run server: `$php bin/console server:run 192.168.1.4:8000` <br />
5) Open `http://192.168.1.4:8000/werewolf`, download and install game on your Android smartphone ([source](https://github.com/PagnoDunadan/WerewolfAndroid)) <br />
6) Open Werewolf Android app and in Options set API_URL to `http://192.168.1.4:8000/` <br />
7) Restart Werewolf Android app and enjoy the game :) <br />

## How to (Full Version)

1) Install Git: `sudo apt-get install git` <br />
   Download server: `git clone https://github.com/PagnoDunadan/WerewolfServer.git` <br />
   Enter directory: `cd WerewolfServer` <br />

2) Install PHP7: `sudo apt-get install php7.0 php7.0-xml php7.0-intl php7.0-mysql` <br />
   or PHP5: `sudo apt-get install php5-cli php5-intl php5-mysql` <br />

3) Install curl: `sudo apt-get install curl` <br />
   Install Composer: `sudo apt install composer` <br />
   or `curl -sS https://getcomposer.org/installer | sudo php -- --install- dir=/usr/local/bin --filename=composer` <br />

4) Install Symfony requirements: `php composer install` <br />

5) Install MySQL: `sudo apt-get install mysql-server` (remember password and enter it in parameters.yml) <br />
   Rename parameters.yml.dist into parameters.yml in /app/config and insert your configuration <br />
   Create database: `$php bin/console doctrine:database:create` <br />
   Preview query: `$php bin/console doctrine:schema:update --dump-sql` <br />
   Execute: `$php bin/console doctrine:schema:update --force` <br />

6) Check your IP: `$ifconfig` (e.g. inet addr:192.168.1.4) <br />
   Run server: `$php bin/console server:run 192.168.1.4:8000` <br />
   Open `http://192.168.1.4:8000/werewolf`, download and install game on your Android smartphone ([source](https://github.com/PagnoDunadan/WerewolfAndroid)) <br />
   Open Werewolf Android app and in Options set API_URL to `http://192.168.1.4:8000/` <br />
   Restart Werewolf Android app and enjoy the game :) <br />