phpMyAdmin's Error Reporting Server
===================================

[![Build Status](https://travis-ci.org/phpmyadmin/error-reporting-server.png?branch=master)](https://travis-ci.org/phpmyadmin/error-reporting-server)
[![Coverage Status](https://coveralls.io/repos/phpmyadmin/error-reporting-server/badge.png)](https://coveralls.io/r/phpmyadmin/error-reporting-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/badges/quality-score.png?s=9d696be27235e042548ad09e1002841b532ee6bb)](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/)

phpMyAdmin server side component for the error reporting system. It uses
CakePHP v2.3 with some extra plugins like migrations, debugkit and OAuth
component.

# How To deploy #

In order to deploy the app in this repo you need to follow these steps:

- Clone this repo or extract the zip file
- Add a virtual hosts entry pointing at the directory where you extracted the
  files in the previous step. Make sure that the installation is in the
  document root.
- Configure the web server (see below)
- Create the database for the server
- install mbstring (required for cake 3.0)
- install intl extension: `sudo apt-get install php5-intl` //(required for cake 3.0)
- cd application_root_dir (directory under which subdirectory `src` resides)
- mkdir tmp;
- mkdir logs;
- set permissions for tmp and logs directory
          ``
	- ```HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1````
	- `setfacl -R -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -m u:${HTTPDUSER}:rwx logs`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx logs`
``
- Rename the example files `config/app_example.php` to
  `config/app.php` and fill out the required info.
  Make sure to change the salts, debug level and
  the database credentials in the `app.php` file.
- Rename the `oauth_example.php` to `oauth.php` and follow the instructions below
  to set the appropriate variables in the file.
- Run the migrations that have been created so far to setup the database
 	- For existing systems: update and run migrations
    	`sudo bin/cake migrations mark_migrated 20150607191654`
    	`sudo bin/cake migrations migrate`
	- For new system: just run migration
	 `sudo bin/cake migrations migrate`

## Requirements ##
 - php >= 5.4
 - MySQL


## Web server setup ##

- Configuration for Apache:
```
<VirtualHost *:80>
			ServerAdmin webmaster@localhost
			ServerName reports.phpmyadmin.net

			<Directory /path/to/repo/dir/webroot/>
				AddType application/x-httpd-php .html
				Options Indexes MultiViews
				AllowOverride All
				Order allow,deny
				allow from all
			</Directory>

			ErrorLog "/var/log/httpd/dummy-host.example.com-error_log"
			CustomLog "/var/log/httpd/dummy-host.example.com-access_log" common
</VirtualHost>
```
- Configuration for lighttpd:
```
$HTTP["host"] =~ "^reports.phpmyadmin.net$" {
			server.document-root = "/srv/http/reports.phpmyadmin.net/webroot/"
			url.rewrite-if-not-file =(
				"^([^\?]*)(\?(.+))?$" => "/index.php?url=$1&$3"
			)
}
```
- Configuration for nginx:
```
server {
        listen [::]:80;
        listen [::]:443 ssl;

        root /home/reports/error-reporting-server/webroot/;
        index index.html index.htm index.php;

        server_name reports.phpmyadmin.net;

        location / {
                # First attempt to serve request as file, then
                # as directory, then fall back to displaying a 404.
                try_files $uri $uri/ /index.html;
        }

        location ~ \.php$ {
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
        #       # With php5-fpm:
                fastcgi_pass unix:/var/run/php5-fpm-reports.sock;
                fastcgi_index index.php;
                include fastcgi_params;
        }

        # CakePHP
        if (!-e $request_filename) {
                rewrite ^/(.+)$ /index.php?url=$1 last;
                break;
        }
}
```

## Oath configuration setup ##

### Creating the GitHub app ###

The application relies on authentication using GitHub. To obtain the client ID
and key, visit [application settings in your Github profile][gh-oauth] and
register an application there.

The callback for the github app should be ``/developers/callback``.

The obtained cliend ID and secret should be stored in the ``config/oauth.php``.

[gh-oauth]: https://github.com/settings/applications

# How to run the test suite #

If you are on a development machine you can use the webrunner at `/test.php`
However if you need a command line runner. You can use:
```
sudo vendor/bin/phpunit -c phpunit.xml.dist
```

# Running the stackhash update shell #

There is a new way of finding unique stacktraces that uses hashes that did not
exist previously. I created a shell to calculate those hashes for old records so
that they can work with the new code. To use the shell you can just type:
```
Console/cake custom addHashesToOldRecords
```

# Cron Jobs #
To Schedule & run cron jobs cakephp provides a console tool. We need to create shell for use in console. We can run the created shell as cron job.
### Execute an Action as Cron Job ###
For example, following is the command to execute the shell src/Shell/StatsShell.php which cache the error reporting statistics for later use.
```Shell
bin/cake stats
```
stats cache will expire in one day so we need to schedule this command to run everyday as cron job.

We need to create new shells to schedule and run different tasks.
### Cron Job Logging ###
A separate log file named `cron_jobs` is maintained for all the cron jobs. All the logging is done via `CakeLog` interface. All the failures and other activity relating to cron jobs should be reported there only. That would make debugging easier in case of failure.
