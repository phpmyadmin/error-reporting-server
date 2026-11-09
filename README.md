phpMyAdmin's Error Reporting Server
===================================

[![Test-suite](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/phpmyadmin/error-reporting-server/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/phpmyadmin/error-reporting-server/branch/master/graph/badge.svg)](https://codecov.io/gh/phpmyadmin/error-reporting-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpmyadmin/error-reporting-server/?branch=master)

phpMyAdmin server side component for the error reporting system. It uses
CakePHP, the migrations plugin and, for the development environment, the
debug kit plugin. The GitHub login is implemented in
`src/Controller/DevelopersController.php`, there is no OAuth plugin.

## Access model

Signing in is done with GitHub, see [OAuth configuration setup](docs/DEPLOYMENT.md#oauth-configuration-setup).
The account does not have to belong to the phpMyAdmin organization:

- Any GitHub account that signs in can view every report and incident, including the raw `full_report` of an incident.
- Only the developers with write access to the repository configured in the `GithubRepoPath` key of ``config/oauth.php`` can act on the reports: forward them to GitHub, change their status, look at the statistics, and use the other actions of the reports, incidents and notifications pages.

A developer without that write access is read-only, the action is refused with a "You need to have commit access on phpmyadmin/phpmyadmin repository on Github.com to do this action" message.
Without signing in, only the report submission endpoint, the pages and the GitHub webhook are reachable.

The write access is read from GitHub at the login and asked again every half day, so a permission granted or removed on the repository is followed without signing out.
When GitHub cannot be asked, the answer of the session is kept — but for a day and a half at most, after which the developer is read-only until GitHub answers again.

## Report submission

Reports are posted to `/incidents/create` as a JSON body, without authentication, and each stored report sends a notification email.
The server therefore bounds a single submission:

- a body larger than 1 MiB (1 048 576 bytes) is refused with the HTTP status `413` and the `{"success": false}` JSON answer, before the body is decoded;
- the string fields of the stored incident and report are cut to the length of their database column, the browser is kept to 40 characters and the error message to 200 in the incident and 150 in the report;
- a report carrying more than 40 errors or more than 40 stack frames keeps the first 40 only;
- a report whose `full_report` or `stacktrace` is larger than a `TEXT` column (65 535 characters) is refused and logged;
- a PHP report without a list of `errors`, or a report of an unmaintained phpMyAdmin version, is ignored.

## Deployment

How to install the server, configure it, migrate its database and run its
cron jobs is in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). The example web
server configurations are in the same `docs` directory.

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
