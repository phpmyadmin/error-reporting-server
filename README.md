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
- Rename the example files `database.example.php` and `core.example.php` to
  `database.php` and `core.php` respectively and fill out the required info.
  Make sure to change the salts and the debug level in the core.php file and
  the database credentials in the `database.php` file.
- Rename the `oauth.example.php` to `oauth.php` and follow the instructions below 
  to set the appropriate variables in the file.
- Run the migrations to generate the migrations table in the `app` directory
  `Console/cake Migrations.migration run all -p Migrations`
- Run the migrations that have been created so far to setup the database 
  in the `app` directory
  `Console/cake Migrations.migration run all --precheck Migrations.PrecheckCondition`


## Web server setup ##

- Configuration for Apache:
```
<VirtualHost *:80>
			ServerAdmin webmaster@localhost
			ServerName reports.phpmyadmin.net

			<Directory /path/to/repo/dir/app/webroot/>
				AddType application/x-httpd-php .html
				Options Indexes FollowSymLinks MultiViews
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
			server.document-root = "/srv/http/reports.phpmyadmin.net/app/webroot/"
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

        root /home/reports/error-reporting-server/app/webroot/;
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
and key, visit application settings in your Github profile and register an
application there.

The callback for the github app should be /developers/callback.

The obtained cliend ID and secret should be stored in the app/Config/oauth.php.

### Creating the SourceForge.net app ###

The reporting bugs to SourceForge.net requires an application registered on
SourceForge.net. You can do this on OAuth tab of your account. The generated
consumer key and secrets  need to be stored in app/Config/oauth.php.

Once you have this, an account which will post bug reports to SourceForge.net
needs to be authorized to do so. For doing this, you can obtain credentias, you
need to visit ``/source_forge/authorize`` URL and store resulting key and
secret in app/Config/oauth.php.

# How to run the test suite #

If you are on a development machine you can use the webrunner at `/test.php`
However if you need a command line runner. You can use:
```
app/Console/cake test app AllTests
```

# Running the stackhash update shell #

There is a new way of finding unique stacktraces that uses hashes that did not
exist previously. I created a shell to calculate those hashes for old records so
that they can work with the new code. To use the shell you can just type:
```
Console/cake custom addHashesToOldRecords
```

## Requirement ##
 - php >= 5.4
 - MySQL 
 
