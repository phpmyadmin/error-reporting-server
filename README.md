phpMyAdmin's Error Reporting Server
===================================

[![Test-suite](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/phpmyadmin/error-reporting-server/branch/master/graph/badge.svg)](https://codecov.io/gh/phpmyadmin/error-reporting-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/?branch=master)

phpMyAdmin server side component for the error reporting system. It uses
CakePHP, the migrations plugin and, for the development environment, the
debug kit plugin. The GitHub login is implemented in
`src/Controller/DevelopersController.php`, there is no OAuth plugin.

# How to deploy

In order to deploy the app in this repo you need to follow these steps:

- Clone this repo or extract the zip file
- Add a virtual hosts entry pointing at the `webroot` directory of the files
  you extracted in the previous step, and not at the directory itself: the
  application code has to stay out of the document root.
- Run `composer install` to download and configure dependencies and library files
- Configure the web server (see [below](#web-server-setup))
- Create the database for the server
- Install the PHP extensions listed in [Requirements](#requirements)
- cd application_root_dir (directory under which subdirectory `src` resides)
- mkdir tmp;
- mkdir logs;
- set permissions for tmp and logs directory
	-     HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
	- `setfacl -R -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx tmp`
	- `setfacl -R -m u:${HTTPDUSER}:rwx logs`
	- `setfacl -R -d -m u:${HTTPDUSER}:rwx logs`
- The `composer install` of the previous step creates `config/app.php` from
  `config/app.default.php` and generates its security salt. If you installed
  the dependencies another way, copy the default configuration file
  `config/app.default.php` to `config/app.php` yourself.
  Make sure to change the salts, debug level and
  the database credentials in the `app.php` file.
- Add the `AcceptedReportsVersions` key to an existing `config/app.php`, it is
  documented in `config/app.default.php`. The reports of the versions that are
  not listed are dropped, and without the key every report is dropped.
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
- The `curl`, `intl`, `json`, `mbstring` and `pdo_mysql` extensions, the same
  list is declared in `composer.json`. On Debian:
  `sudo apt-get install php-curl php-intl php-mbstring php-mysql`
- MySQL or MariaDB. The continuous integration runs the MariaDB version
  packaged in Debian 13 trixie (11.8).

## Web server setup

The application is served from the `webroot` directory, and the web server has
to send every request to `webroot/index.php`. Example configurations are kept
in the `docs` directory:

- [Apache](docs/apache.conf)
- [Caddy](docs/Caddyfile)
- [lighttpd](docs/lighttpd.conf)
- [nginx](docs/nginx.conf)

See [docs/server-setup.md](docs/server-setup.md) for the document root, the
permissions, the PHP-FPM pool and the application URL.

## OAuth configuration setup

### Creating the GitHub app

The application relies on authentication using GitHub. To obtain the client ID
and key, visit [application settings in your Github profile][gh-oauth] and
register an application there.

The callback for the github app should be ``<http://YOUR_PREFERRED_DOMAIN>/developers/callback`` where ``YOUR_PREFERRED_DOMAIN`` is the URL you wish to access the local instance on.

Copy the example configuration in ``config/oauth_example.php`` to ``config/oauth.php`` and replace the dummy credentials with the obtained client ID and secret.

[gh-oauth]: https://github.com/settings/developers

## Github Events

The service files a GitHub issue for a report once a developer forwards it, and
it keeps the status of the report up to date from the state of that issue: when
somebody closes or reopens the issue, the matching report follows.

Add the webhook to the repository named by the `GithubRepoPath` key of
``config/oauth.php``, with `https://<host>:<port>/events` as payload URL, the
`Issues` events and the secret token of the `GithubWebhookSecret` key.

See [docs/github-webhook.md](docs/github-webhook.md) for the whole setup, the
statuses it changes and the answers it sends.

## Sync Github Issue state

- Get a Github Personal Access token as explained [here](https://help.github.com/articles/creating-a-personal-access-token-for-the-command-line/)
- You need to set the `repo` scope for this token.
- Set value of obtained `GithubAccessToken` in ``config/oauth.php``
- After setting value of `GithubAccessToken` in ``config/oauth.php`` as explained above, you can run the synchronization action as

```sh
bin/cake sync_github_issue_states
```

- This can be scheduled as a cron job too.

## How to run the test suite

You can run the test suite with:

```sh
composer run test --timeout=0
```

The coding standard and the static analysis can be run with:

```sh
composer run phpcs
composer run phpstan
```

## Cron Jobs

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

A separate log file, `logs/cron_jobs.log`, is maintained for all the cron jobs:
the code logs with the `cron_jobs` scope and the scope is configured in the
`Log` section of ``config/app.php``. The logging is done via the `Log` class.
All the failures and other activity relating to cron jobs should be reported
there only. That makes debugging easier in case of a failure.
