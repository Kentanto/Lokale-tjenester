# Staging / Experimental files

This folder contains safe, non-destructive prototype endpoints and helpers for development.
They are intentionally separate from the main site so you can try features without touching
production code.

Files included:

- `feed.php` — read-only jobs feed returning JSON. Uses the same DB as the app but only selects
  commonly available columns (safe against missing `category`).
- `mailer_stub.php` — a safe mailer demo that shows how to send verification mails (does not
  enable mail by default; simulates send when `?simulate=1`).
- `admin_stub.php` — a small admin-area placeholder showing where admin functionality
  can be prototyped.

How to use:

1. Place this repository on your dev server.
2. Visit `http://your-host/staging/feed.php` to fetch the sample feed (returns JSON).
3. Use these files for local testing and iterate; when ready, we can integrate pieces into the
   main app with careful migrations and tests.

Security notes:

- These files are for development only. Do not deploy the `staging/` folder to a public production
  environment without access controls.
- All database queries here are read-only or simulated. They use prepared statements where
  applicable and handle missing columns gracefully.

If you want, I can add a `staging/profiles.php`, `staging/ratings.php`, and other feature
stubs next.

— Staging team
