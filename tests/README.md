# Tests

This folder contains lightweight tests for the PHP login project.

## Run automated tests

From the project root:

```bash
php tests/run.php
```

The local auth tests use temporary JSON stores under the system temp directory, so they do not modify the real app data in `data/users.json`.

## Run API tests

The API tests are meant to run against an app instance that is already running somewhere else. Set `BASE_URL` to that test instance before running PHPUnit:

```bash
BASE_URL=https://your-test-app.example phpunit --testsuite API
```

Use a disposable test environment for API tests because registration scenarios create test users and task scenarios create task records.

## Current automated coverage

- default seeded admin account exists
- password strength validation
- user registration
- duplicate username rejection
- login success
- login failure
- logout session clearing
- API page loading for `/`, `/registration/`, and `/styles.css`
- API protected page redirects for `/profile/`, `/settings/`, and `/activity/`
- API authenticated page loading for the post-login profile, settings, and activity pages
- API login redirects, session cookies, and cross-client session isolation
- API registration success, duplicate usernames, empty usernames, trimmed usernames, weak password variants, and login after registration
- API authenticated registration redirect behavior
- API logout behavior for `POST /logout.php` and `GET /logout.php`
- API escaping of registered usernames on the dashboard
- JSON API authentication checks for `/api/session.php`
- JSON API profile reads, updates, and validation errors
- JSON API settings reads, updates, and validation errors
- JSON API task list, create, patch, delete, 401, 404, and 405 behavior

## Manual tests to perform

### Login flow

- login with `admin` / `password`
- login with a wrong password
- login with an unknown username
- login with empty username
- login with empty password
- login after registering a new user
- navigate to Profile, Settings, and Activity after login
- update profile details from the Profile page
- update theme, notifications, and timezone from the Settings page
- create, complete, reopen, and delete tasks from the Activity page

### Registration flow

- register a valid new account
- register the same username twice
- register with a short password
- register without a number in the password
- register without a special character in the password
- register with an empty username
- register with leading or trailing spaces in the username

### Session and logout

- refresh the page after login and confirm the dashboard stays visible
- open the app in a new tab after login and confirm the session is shared
- logout and confirm the dashboard is no longer visible
- use the browser back button after logout and confirm protected content is not restored

### Data and edge cases

- delete `data/users.json` and confirm the app recreates the default admin user
- register usernames with mixed case like `TestUser`
- register usernames with symbols or spaces and decide whether you want to allow them
- create many users in a row and confirm the JSON store remains valid
- try concurrent registrations in two browser tabs with the same username

## Good next tests to add later

- browser tests with Playwright or Cypress
- validation tests for stricter username rules if you add them
