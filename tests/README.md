# Tests

This folder contains lightweight tests for the PHP login project.

## Run automated tests

From the project root:

```bash
php tests/run.php
```

The tests use a temporary JSON user store in `tests/tmp/users.test.json`, so they do not modify the real app data in `data/users.json`.

## Current automated coverage

- default seeded admin account exists
- password strength validation
- user registration
- duplicate username rejection
- login success
- login failure
- logout session clearing

## Manual tests to perform

### Login flow

- login with `admin` / `password`
- login with a wrong password
- login with an unknown username
- login with empty username
- login with empty password
- login after registering a new user

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

- endpoint tests for `index.php`, `registration/index.php`, and `logout.php`
- browser tests with Playwright or Cypress
- validation tests for stricter username rules if you add them
