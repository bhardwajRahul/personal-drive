# Won't Fix

## C1 — Multi-folder share path

Won't fix. Current UI lets users select files from one folder only, so multi-folder selection is not reachable behavior.

## C3 — Guest session invalidation

Won't fix. Existing session behavior is expected for paused, re-enabled, deleted, or recreated shares.

## D2 — 2FA QR regeneration

Won't fix. Scanning a newly generated QR resolves an incorrect OTP.

## D3 — Multiple admins under disabled auth

Won't fix. App supports one admin.

## E1 — Double extensions / content-extension mismatch

Won't fix. Docker serves only `public/`; normal uploads use `/var/www/html/personal-drive-storage-folder`. HTML and SVG fetches download as attachments with `nosniff`.

## F1 — DISABLE_AUTH reliability

Won't fix. Runtime `DISABLE_AUTH` survives config caching, automatically authenticates the admin, and hides the 2FA option. Existing tests cover disabled-auth access.

## F2 — Docker public permissions

Won't fix. The Docker image sets `public/` ownership and mode `755` during build. Standard deployment does not mount or replace this directory.
