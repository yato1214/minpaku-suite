
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

## MVP Scope (locked)
- Settings page: CPT slug, ICS import URLs, export route `/ics/{post_id}.ics`, interval (hourly/2hours/6hours), manual sync button
- ICS export endpoint
- ICS import (cron + manual)
- Logs UI (last run, counts, recent errors)
