# Server setup

This page explains how to serve the application. The configuration files of the
supported web servers are next to it:

- [apache.conf](apache.conf)
- [Caddyfile](Caddyfile)
- [lighttpd.conf](lighttpd.conf)
- [nginx.conf](nginx.conf)

They are examples: replace `reports.phpmyadmin.net` by the hostname the server
answers to, and `/path/to/repo/dir` by the directory the repository was
extracted to.

## Document root

The document root is the **`webroot`** directory, never the directory of the
repository. Everything that is not meant to be served stays above it:

| Path | Served | Content |
| ---- | ------ | ------- |
| `webroot/` | yes | the only directory the web server reads |
| `config/` | no | `app.php` with the security salt, `oauth.php` with the tokens |
| `logs/`, `tmp/` | no | the logs and the caches of the application |
| `src/`, `vendor/` | no | the application and its dependencies |

A document root set on the repository itself would serve `config/app.php`, and
with it the security salt and the GitHub tokens.

## Permissions

The user the PHP process runs as needs to write in two directories:

```sh
chown -R www-data:www-data logs/ tmp/
```

The rest of the files only need to be readable. If the application is updated
with git, make sure the directories created later keep the same owner.

## PHP-FPM

The examples use a PHP-FPM pool through its unix socket,
`/var/run/php8.4-fpm-reports.sock`. Create the pool in
`/etc/php/8.4/fpm/pool.d/reports.conf`:

```ini
[reports]
user = www-data
group = www-data
listen = /var/run/php8.4-fpm-reports.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
```

Adjust the socket path everywhere it is used: the web server configuration and
`fastcgi_pass` on nginx, `php_fastcgi` on Caddy, `fastcgi.server` on lighttpd.

## Application URL

Set `App.fullBaseUrl` to the URL the server is reached at, with the
`APP_FULL_BASE_URL` environment variable or the key of ``config/app.php``. The
default configuration reads the environment variable and falls back to `false`:

```php
'App' => [
    //'fullBaseUrl' => env('APP_FULL_BASE_URL', false),
    'fullBaseUrl' => 'https://reports.phpmyadmin.net',
],
```

It is used to build the links and to check the `Host` header of a request: a
request for another host is refused with a `400`, which includes the deliveries
of the [GitHub webhook](github-webhook.md). When the key is missing the
application fails with an internal error as soon as `debug` is off.

The HTTPS redirection and the certificates are left to the web server.

## Rewrites

The application is reached through `webroot/index.php`, every URL is handled by
it:

- Apache uses the `.htaccess` shipped in `webroot/`, so the `AllowOverride All`
  of [apache.conf](apache.conf) is required. Without it every page answers
  `404` except the home page.
- nginx, Caddy and lighttpd have no `.htaccess`, the rewrite is in their
  configuration file.
