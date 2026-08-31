# Security Policy

## Supported versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

Only the latest minor release of the current major version receives security fixes.

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report vulnerabilities privately through GitHub Security Advisories:

<https://github.com/maciej-kosiedowski/IndexNow-for-Laravel/security/advisories/new>

Please include:

* a description of the problem and its impact,
* the affected version(s) of this package, of Laravel and of PHP,
* steps to reproduce, ideally a minimal reproducer.

You will get an acknowledgement within 7 days. Once a fix is ready a patch release is published and
the advisory is disclosed together with a credit, unless you prefer to stay anonymous.

If the problem is in the protocol handling rather than in the Laravel integration, report it against
[slimad/indexnow](https://github.com/maciej-kosiedowski/IndexNow/security/advisories/new) instead.

## Scope

Two things are worth keeping in mind when assessing an issue:

* the IndexNow key is **not** a secret. The protocol requires it to be publicly readable at the
  configured `key_location`, and the optional key route serves it deliberately. It is an ownership
  proof, not a credential;
* the package never executes remote content — responses are only inspected for their HTTP status
  code and echoed (truncated) into exception messages and events.

Things that *are* in scope: anything that would let a request reach a URL it should not, leak
application data into a submission, or turn the key route into a way of reading arbitrary
configuration.
