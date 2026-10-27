phpMyAdmin's Error Reporting Server
===================================

[![Test-suite](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/phpmyadmin/error-reporting-server/branch/master/graph/badge.svg)](https://codecov.io/gh/phpmyadmin/error-reporting-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/?branch=master)

phpMyAdmin server side component for the error reporting system. It uses
CakePHP with some extra plugins like migrations, debugkit and OAuth
component.

# How to deploy

In order to deploy the app in this repo you need to follow these steps:

- Clone this repo or extract the zip file
- Add a virtual hosts entry pointing at the directory where you extracted the
  files in the previous step. Make sure that the installation is in the
  document root.
- Run `composer install` to download and configure dependencies and library files
- Configure the web server (see [below](#oauth-configuration-setup))
- Create the database for the server
- install the mbstring and intl extensions, on Debian use:
  `sudo apt-get install php-mbstring php-intl`
- cd application_root_dir (directory under which subdirectory `src` resides)
- mkdir tmp;
- mkdir logs;
- set permissions for tmp and logs directory
	-     HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
	- `setfacl -R -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -m u:${HTTPDUSER}:rwx logs`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx logs`
- Copy the default configuration file `config/app.default.php` to
  `config/app.php` and fill out the required info.
  Make sure to change the salts, debug level and
  the database credentials in the `app.php` file.
- Copy the `oauth_example.php` to `oauth.php` and follow the instructions below
  to set the appropriate variables in the file.
- Copy the `forwarding_example.php` to `forwarding.php` and fill the values.
- Run the migrations that have been created so far to setup the database
 	- For existing systems: update and run migrations
    	`bin/cake migrations mark_migrated 20150607191654`
    	`bin/cake migrations migrate`
	- For new system: just run migration
	 `bin/cake migrations migrate`

## Requirements

- php >= 8.4
- MySQL


## Web server setup

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
        index index.php;

        server_name reports.phpmyadmin.net;

        location / {
                # First attempt to serve request as file, then
                # as directory, then fall back to displaying a 404.
                try_files $uri $uri/ /index.php?$args;
        }

        location ~ \.php$ {
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
        #       # With php7-fpm:
                fastcgi_pass unix:/var/run/php8.4-fpm-reports.sock;
                fastcgi_index index.php;
                include fastcgi_params;
        }
}
```
- Configuration for Caddy:
```Caddyfile
https://reports.phpmyadmin.net {
    # Set this path to your site's directory.
    root * /home/reports/error-reporting-server/webroot/
    php_fastcgi unix//var/run/php8.4-fpm-reports.sock

    # Enable the static file server.
    encode gzip
    file_server

    try_files {path} {path}/ /index.php?{query}
}

```

## OAuth configuration setup

### Creating the GitHub app

The application relies on authentication using GitHub. To obtain the client ID
and key, visit [application settings in your Github profile][gh-oauth] and
register an application there.

The callback for the github app should be ``<http://YOUR_PREFERRED_DOMAIN>/developers/callback`` where ``YOUR_PREFERRED_DOMAIN`` is the URL you wish to access the local instance on.

Copy the example configuration in ``config/oauth.example.php`` to ``config/oauth.php`` and replace the dummy credentials with the obtained client ID and secret.

[gh-oauth]: https://github.com/settings/developers

## Github Events

The service files a GitHub issue for a report once a developer forwards it, and
it uses that issue to keep the status of the report up to date: when somebody
closes or reopens the issue, the matching report follows.

The issues are filed in the repository named by the `GithubRepoPath` key of
``config/oauth.php``, which is the same repository used to check the commit
access of the developers. Add the webhook to that repository:

- Add a [webhook](https://developer.github.com/webhooks/creating/) at your
  [target repository](https://github.com/phpmyadmin/phpmyadmin) with payload URL
  as `https://<host>:<port>/events`
  - Select content-type as `application/json`
  - Select `Issues` events from available events
  - Set the secret token value

- Set the appropriate value of secret token in the `GithubWebhookSecret` key of config/app.php (same as what you set while setting up the webhook)

The secret and the repository are in two different files: `GithubWebhookSecret`
lives in ``config/app.php``, `GithubRepoPath` in ``config/oauth.php``.

### What the service does with a delivery

Only the `Issues` events are read, and only the state of the issue is of
interest:

| Action of the issue | New status of the report |
| ------------------- | ------------------------ |
| `closed` | `resolved` |
| `opened` | `forwarded` |
| `reopened` | `forwarded` |

The issue is linked to its reports by its number, which is stored in the
`sourceforge_bug_id` column of the reports table. The column kept its
SourceForge name, it holds the GitHub issue number.

- A delivery for an action that is not in the table is ignored, the service
  answers `204 No Content`.
- A delivery for an issue that no report is linked to is ignored as well, with
  the same answer. The repository receives an event for every one of its
  issues, not only the ones filed here, so this is the normal case.
- The request has to be sent by GitHub: the `User-Agent` header must start with
  `GitHub-Hookshot`, every other request is refused with `403`. A `ping` event
  answers `200` before any signature check, it only tells GitHub that the
  endpoint is reachable.
- The signature of the payload is read from the `X-Hub-Signature` header, the
  SHA-1 one. GitHub also sends it until it is removed, together with the
  `X-Hub-Signature-256` header this service does not read yet.

The `App.fullBaseUrl` setting has to be filled, with the `APP_FULL_BASE_URL`
environment variable or the key of ``config/app.php``: without it every request
fails with an internal error when `debug` is off. The `Host` header of the
delivery is compared to the host of that URL too, a delivery for another host is
answered with `400`.

### When a delivery is missed

The webhook is the real time path. If the hook was down, or if an issue was
closed before it was set up, run the synchronization action described below to
read the state of every issue again.


## Sync Github Issue state

- Get a Github Personal Access token as explained [here](https://help.github.com/articles/creating-a-personal-access-token-for-the-command-line/)
- You need to set the `repo` scope for this token.
- Set value of obtained `GithubAccessToken` in config/oauth.php
- After setting value of GithubAccessToken in config/oauth.php as explained above, you can run the synchronization action as

```sh
./bin/cake sync_github_issue_states
```

- This can be scheduled as a cron job too.

# How to run the test suite

You can run the test suite with:

```sh
composer run test --timeout=0
```

The coding standard and the static analysis can be run with:

```sh
composer run phpcs
composer run phpstan
```

# Cron Jobs

To schedule and run cron jobs, CakePHP provides a console tool. A command can be
run as a cron job.

### Execute an Action as Cron Job
For example, following is the command to execute src/Command/StatsCommand.php
which caches the error reporting statistics for later use.

```sh
bin/cake stats
```

The statistics cache expires in one day so we need to schedule this command to
run everyday as cron job.

### Cron Job Logging

A separate log file named `cron_jobs` is maintained for all the cron jobs. The
logging is done via the `Log` class. All the failures and other activity relating
to cron jobs should be reported there only. That makes debugging easier in case
of a failure.
