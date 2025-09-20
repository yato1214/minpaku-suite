
# Minpaku Suite Starter

This repository contains a ready-to-run WordPress environment via `@wordpress/env` and a starter plugin **Minpaku Channel Sync**.

## Quick Start
```bash
npm i -g @wordpress/env
wp-env start
# open http://localhost:8888/wp-admin  (admin / password)
```

To hack with aider (Claude):
```bash
pipx install aider-chat
# export ANTHROPIC_API_KEY=sk-ant-xxxx
aider plugins/minpaku-channel-sync/minpaku-channel-sync.php plugins/minpaku-channel-sync/includes/*.php
```

## Folders
- `plugins/minpaku-channel-sync` - the plugin
- `tools/scripts` - release scripts

## REST API

The MinPaku Suite provides a read-only REST API under the `minpaku/v1` namespace:

### Endpoints

**Availability Check**
```
GET /wp-json/minpaku/v1/availability
```
Parameters:
- `property_id` (required): Property ID to check
- `from` (required): Start date in Y-m-d format
- `to` (required): End date in Y-m-d format

**Quote Calculation**
```
GET /wp-json/minpaku/v1/quote
```
Parameters:
- `property_id` (required): Property ID
- `checkin` (required): Check-in date in Y-m-d format
- `checkout` (required): Check-out date in Y-m-d format
- `adults` (optional): Number of adult guests (default: 1)
- `children` (optional): Number of child guests (default: 0)

**API Information**
```
GET /wp-json/minpaku/v1/
```
Returns complete API documentation, rate limits, and usage examples.

### Features
- Public access (no authentication required)
- Rate limiting (60 requests/minute, 1000/hour per IP)
- CORS enabled for external system integration
- Caching headers (5 min for availability, 1 min for quotes)
- Comprehensive error handling with HTTP status codes

### Testing
Run the test script to validate API functionality:
```bash
php test-api.php
```

## MVP Scope (locked)
- Settings page: CPT slug, ICS import URLs, export route `/ics/{post_id}.ics`, interval (hourly/2hours/6hours), manual sync button
- ICS export endpoint
- ICS import (cron + manual)
- Logs UI (last run, counts, recent errors)
