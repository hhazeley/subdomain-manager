# Changelog

All notable changes to Subdomain Manager are documented here.

## 1.1.1

- Fixed GitHub release and test workflows to compile Vite assets before running view-rendering tests.

## 1.1.0

- Added administrator approval as a second access gate after email verification.
- Added administrator-created user accounts with verification and password setup emails.
- Added guarded user deletion that refuses to delete users who own subdomains.
- Added a persistent administrator setting to enable or disable self-registration.

## 1.0.0

- Initial cPanel-ready release.
- User registration with verified email and reCAPTCHA.
- Administrator-approved subdomain registration and annual renewal.
- Cloudflare DNS record management.
- Administrator roles, blocked labels, delayed deletion, and lifecycle notifications.
- Account settings, themes, browser installer, semantic versioning, and GitHub release updates.
