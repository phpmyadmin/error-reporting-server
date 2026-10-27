# GitHub webhook

The service files a GitHub issue for a report once a developer forwards it, and
it uses that issue to keep the status of the report up to date: when somebody
closes or reopens the issue, the matching report follows.

## Which repository

The issues are filed in the repository named by the `GithubRepoPath` key of
``config/oauth.php``, which is the same repository used to check the commit
access of the developers. Add the webhook to that repository.

## Creating the hook

- Add a [webhook](https://developer.github.com/webhooks/creating/) at your
  [target repository](https://github.com/phpmyadmin/phpmyadmin) with payload URL
  as `https://<host>:<port>/events`
  - Select content-type as `application/json`
  - Select `Issues` events from available events
  - Set the secret token value

- Set the appropriate value of secret token in the `GithubWebhookSecret` key of
  ``config/app.php`` (same as what you set while setting up the webhook)

The secret and the repository are in two different files: `GithubWebhookSecret`
lives in ``config/app.php``, `GithubRepoPath` in ``config/oauth.php``.

## What the service does with a delivery

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
  SHA-1 one, and it covers the body of the delivery. GitHub also sends it until
  it is removed, together with the `X-Hub-Signature-256` header this service
  does not read yet.

## Answers

| Status | When | Body |
| ------ | ---- | ---- |
| `200` | `ping` event | none |
| `201` | a report was updated | none |
| `204` | the delivery changes nothing | none |
| `400` | the event is not an `Issues` one, or the `Host` header does not match | `{"error": "..."}` |
| `401` | the signature is missing, unknown or wrong | `{"error": "..."}` |
| `403` | the request does not come from GitHub | `{"error": "..."}` |

A response without content carries no `Content-Type` header: the field
describes the content of the message (RFC 9110, section 8.3), and a `204` must
not have any content at all (section 15.3.5). The refusals carry a small JSON
body, so their `application/json` content type is accurate and the reason can be
read in the "Recent Deliveries" of the hook.

GitHub only reads the status code, an answer that is not a `2xx` marks the
delivery as failed and it can be redelivered from the interface.

The `App.fullBaseUrl` setting has to be filled, with the `APP_FULL_BASE_URL`
environment variable or the key of ``config/app.php``: without it every request
fails with an internal error when `debug` is off. The `Host` header of the
delivery is compared to the host of that URL too, a delivery for another host is
answered with `400`. See [the server setup](server-setup.md) for the value to use.

## When a delivery is missed

The webhook is the real time path. If the hook was down, or if an issue was
closed before it was set up, run the synchronization action to read the state of
every issue again:

```sh
bin/cake sync_github_issue_states
```

It needs a GitHub personal access token with the `repo` scope in the
`GithubAccessToken` key of ``config/oauth.php``, see the
[README](../README.md#sync-github-issue-state).
