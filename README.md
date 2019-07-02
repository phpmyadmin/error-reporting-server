phpMyAdmin's Error Reporting Server
===================================

[![Build Status](https://travis-ci.org/phpmyadmin/error-reporting-server.png?branch=master)](https://travis-ci.org/phpmyadmin/error-reporting-server)
[![codecov](https://codecov.io/gh/phpmyadmin/error-reporting-server/branch/master/graph/badge.svg)](https://codecov.io/gh/phpmyadmin/error-reporting-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/badges/quality-score.png?s=9d696be27235e042548ad09e1002841b532ee6bb)](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/)

phpMyAdmin server side component for the error reporting system. It uses
CakePHP with some extra plugins like migrations, debugkit and OAuth
component.

# How To deploy #

In order to deploy the app in this repo you need to follow these steps:

- Clone this repo or extract the zip file
- Add a virtual hosts entry pointing at the directory where you extracted the
  files in the previous step. Make sure that the installation is in the
  document root.
- Run `composer install` to download and configure dependencies and library files
- Configure the web server (see [below](#oauth-configuration-setup))
- Create the database for the server
- install mbstring (required for cake 3.0)
- install intl extension; on Debian use: `sudo apt-get install php-intl` //(required for cake 3.0)
- cd application_root_dir (directory under which subdirectory `src` resides)
- mkdir tmp;
- mkdir logs;
- set permissions for tmp and logs directory
	-     HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
	- `setfacl -R -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -m u:${HTTPDUSER}:rwx logs`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx logs`
- Copy the example files `config/app_example.php` to
  `config/app.php` and fill out the required info.
  Make sure to change the salts, debug level and
  the database credentials in the `app.php` file.
- Copy the `oauth_example.php` to `oauth.php` and follow the instructions below
  to set the appropriate variables in the file.
- Run the migrations that have been created so far to setup the database
 	- For existing systems: update and run migrations
    	`bin/cake migrations mark_migrated 20150607191654`
    	`bin/cake migrations migrate`
	- For new system: just run migration
	 `bin/cake migrations migrate`

## Requirements ##
 - php >= 7.1
 - MySQL


## Web server setup ##

- Configuration for Apache (this will run the server on port 80, if you
  already have services on port 80 you may wish to use a different port
  or configuration method):
```apache
<VirtualHost *:80>
			ServerAdmin webmaster@localhost
			ServerName reports.phpmyadmin.net

			<Directory /path/to/repo/dir/webroot/>
				AddType application/x-httpd-php .html
				Options Indexes MultiViews
				Require all granted
			</Directory>

			ErrorLog "/var/log/httpd/dummy-host.example.com-error_log"
			CustomLog "/var/log/httpd/dummy-host.example.com-access_log" common
</VirtualHost>
```
- Configuration for lighttpd:
```lighttpd
$HTTP["host"] =~ "^reports.phpmyadmin.net$" {
			server.document-root = "/srv/http/reports.phpmyadmin.net/webroot/"
			url.rewrite-if-not-file =(
				"^([^\?]*)(\?(.+))?$" => "/index.php?url=$1&$3"
			)
}
```
- Configuration for nginx:
```nginx
server {
        listen [::]:80;
        listen [::]:443 ssl;

        root /home/reports/error-reporting-server/webroot/;
        index index.html index.htm index.php;

        server_name reports.phpmyadmin.net;

        location / {
                # First attempt to serve request as file, then
                # as directory, then fall back to displaying a 404.
                try_files $uri $uri/ /index.php?$args;
        }

        location ~ \.php$ {
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
        #       # With php5-fpm:
                fastcgi_pass unix:/var/run/php5-fpm-reports.sock;
                fastcgi_index index.php;
                include fastcgi_params;
        }
}
```

## OAuth configuration setup ##

### Creating the GitHub app ###

The application relies on authentication using GitHub. To obtain the client ID
and key, visit [application settings in your Github profile][gh-oauth] and
register an application there.

The callback for the github app should be ``<http://YOUR_PREFERRED_DOMAIN>/developers/callback`` where ``YOUR_PREFERRED_DOMAIN`` is the URL you wish to access the local instance on.

Copy the example configuration in ``config/oauth.example.php`` to ``config/oauth.php`` and replace the dummy credentials with the obtained cliend ID and secret.

[gh-oauth]: https://github.com/settings/developers


## Github Events ##
- Add a [webhook](https://developer.github.com/webhooks/creating/) at your [target repository](https://github.com/phpmyadmin/phpmyadmin) with payload URL as `https://<host>:<port>/events`
  - Select content-type as `application/json`
  - Select `Issues` events from available events
  - Set the secret token value

- Set the appropriate value of secret token in app.php (same as what you set while setting up the webhook)


## Sync Github Issue state ##
- Get a Github Personal Access token as explained [here](https://help.github.com/articles/creating-a-personal-access-token-for-the-command-line/)
- Set value of obtained `GithubAccessToken` in config/oauth.php
- After setting value of GithubAccessToken in config/oauth.php as explained above, you can run the synchronization action as
```shell
./bin/cake sync_github_issue_states
```
- This can be scheduled as a cron job too.

# How to run the test suite #

If you are on a development machine you can use the webrunner at `/test.php`
However if you need a command line runner. You can use:
```shell
composer run test --timeout=0
```

# Running the stackhash update shell #

There is a new way of finding unique stacktraces that uses hashes that did not
exist previously. I created a shell to calculate those hashes for old records so
that they can work with the new code. To use the shell you can just type:
```shell
Console/cake custom addHashesToOldRecords
```

# Cron Jobs #
To Schedule & run cron jobs cakephp provides a console tool. We need to create shell for use in console. We can run the created shell as cron job.
### Execute an Action as Cron Job ###
For example, following is the command to execute the shell src/Shell/StatsShell.php which cache the error reporting statistics for later use.
```shell
bin/cake stats
```
stats cache will expire in one day so we need to schedule this command to run everyday as cron job.

We need to create new shells to schedule and run different tasks.
### Cron Job Logging ###
A separate log file named `cron_jobs` is maintained for all the cron jobs. All the logging is done via `CakeLog` interface. All the failures and other activity relating to cron jobs should be reported there only. That would make debugging easier in case of failure.
