# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | Yes                |

This is an early-stage project (v0.1.0). Security fixes are applied to the
current `main` branch.

## Reporting a Vulnerability

Do not open a public GitHub issue for a security vulnerability.

Report it by opening a private issue through GitHub, or through the contact
form at https://mrpythonist.com. Include:

- A description of the vulnerability and where it is
- Steps to reproduce it, if you have them
- The impact you think it has (what an attacker could do with it)
- Whether you have seen it exploited

You should get an acknowledgement within 7 days. If the report is accepted,
a fix will be prepared and released, and you will be credited if you want to
be.

## Scope and Notes

- The API has no authentication in v0.1.0. It is a single-user app meant to
  run on localhost or a private network. Do not expose it to the public
  internet without adding authentication first. See the Security section in
  the README.
- Uploaded CSVs can contain real billing data. Never share a real AWS
  billing export in an issue, a pull request, or a security report. Use the
  synthetic file in `sample-data/` or a redacted sample.
- The sample data in this repo is synthetic. It contains no real account
  information.
- Do not include credentials, AWS keys, or tokens in any report. Rotate any
  credential that has been shared by accident.
