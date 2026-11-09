Deploying the server
====================

Everything a deployment has to do: the packages to install, the configuration
files to create, the migrations to run, the web server to point at `webroot`,
and the cron jobs that keep the statistics, the GitHub states and the tracker
forwarding up to date.

## How to deploy

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
  It forwards the accepted reports to a sentry compatible tracker, one event per
  error, using the sentry envelope protocol. Leave `Forwarding.Sentry` null to
  disable the forwarding: the reports are stored and notified as usual.
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

- [Apache](apache.conf)
- [Caddy](Caddyfile)
- [lighttpd](lighttpd.conf)
- [nginx](nginx.conf)

See [server-setup.md](server-setup.md) for the document root, the
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

See [github-webhook.md](github-webhook.md) for the whole setup, the
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

### Forward the reports to the tracker

When `Forwarding.Sentry` is set, the reports are sent to the tracker by a
command rather than by the request that stored them, so that a slow tracker
never delays the answer to the client nor the notification mail:

```sh
bin/cake forward_reports
```

It reads every incident whose `forwarded_at` is empty, a hundred at a time, and
sets it on each one it is done with. An incident the tracker refused keeps its
empty `forwarded_at`, so the next run tries again — a backlog, and the sign that
the tracker is refusing what it is sent, are both visible as:

```sql
SELECT COUNT(*) FROM incidents WHERE forwarded_at IS NULL;
```

Before the first run, decide what happens to the occurrences stored before the
column existed: they all have an empty `forwarded_at`, so the first run forwards
the whole archive unless you mark it as already handled.

```sql
UPDATE incidents SET forwarded_at = created;   -- skip the archive
```

Run it every minute or five minutes as a cron job. A run that has nothing to do,
or that finds another run already going, prints nothing.

**Nothing forwards without the cron.** The ingest endpoint no longer calls the
tracker: it stores the report and answers, and the command above is the only thing
that sends anything. Deploy the code without the crontab entry and reports keep
arriving and keep being stored, silently, with nothing reaching the tracker. The
backlog query is the check that the command runs at all.

**Every occurrence is forwarded.** A repeat of the same error adds an incident to
the existing report, and each incident is sent to the tracker: an issue keeps
showing that it still happens, with the count of events behind it. The report page
here keeps the full history either way.

**Only one run at a time.** The command takes a `flock()` on `tmp/forward_reports.lock`
and exits silently when it is held; the file holds the PID of the run that has it.
`flock` is released if the process dies, so there is no stale lock to clear.

The lock file lives in `tmp/`, which the user the cron runs as has to be able to
write: if it cannot, every run fails at once instead of doing any work. The lock
assumes one host too — `flock` over NFS is not reliable, and several hosts sharing
the queue need a lock they share (the Redis engine of `Cake\Lock`, or a MySQL
`GET_LOCK`).

**Output follows the console verbosity:**

| Flags | What is printed |
| ----- | --------------- |
| none | nothing on stdout; the tracker's own transport error still goes to stderr |
| `-v` | one line per incident (`Incident 12: 4628 bytes, 1 event(s)`), one per refusal, and a summary |
| `--debug` | everything `-v` prints, plus the event ids |
| `-q` | nothing on stdout; stderr keeps the failures |

`-vvv` is accepted by CakePHP but read as `-v`: the framework has no third level,
which is why `--debug` exists.

**A refused incident produces no output of its own**: it is written to
`logs/cron_jobs.log` and printed by `-v` on stdout, and the run still exits 0.
Only a transport failure raised by the tracker itself reaches stderr, and that is
what a cron would mail about — repeatedly, since the incident stays pending until
it is forwarded. Watch the backlog query rather than the cron mail: a queue that
grows is the reliable signal, whatever the tracker answers.

### Cron Job Logging

A separate log file, `logs/cron_jobs.log`, is maintained for all the cron jobs:
the code logs with the `cron_jobs` scope and the scope is configured in the
`Log` section of ``config/app.php``. The logging is done via the `Log` class.
All the failures and other activity relating to cron jobs should be reported
there only. That makes debugging easier in case of a failure.
