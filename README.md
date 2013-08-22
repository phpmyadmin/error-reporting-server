Error Reporting Server
======================

phpmyadmin server side component for the error reporting system. It uses
CakePHP v2.3 with some extra plugins like migrations, debugkit and OAuth
component.

# How To deploy #

In order to deploy the app in this repo you need to follow these steps:

- Clone this repo or extract the zip file
- Add a virtual hosts entry pointing at the directory where you extracted the
  files in the previous step. Make sure that the installation is in the
  document root. 

    - For apache it should look similar to this:
```
<VirtualHost *:80>
			ServerAdmin webmaster@localhost
			ServerName reports.phpmyadmin.net

			DocumentRoot /path/to/repo/dir/app/webroot
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
- Create the database for the server
- Rename the example files `database.example.php` and `core.example.php` to
  `database.php` and `core.php` respectively and fill out the required info.
  Make sure to change the salts and the debug level in the core.php file and
  the database credentials in the `database.php` file.
- Rename the `oauth.example.php` to `oauth.php` and follow the instructions to
  set the appropriate variables in the file.
- Run the migrations to generate the migrations table in the `app` directory
  `Console/cake Migrations.migration run all -p Migrations`
- Run the migrations that have been created so far to setup the database 
  in the `app` directory
  `Console/cake Migrations.migration run all`

## Creating the github app ##
The callback for the github app should be /developers/callback
