# CalDAV Client for Dolibarr

[Français](README.md) | English

Connect external CalDAV calendars to the Dolibarr agenda and synchronize events in either direction. The module also provides calendar display options and an upcoming-events widget.

This guide describes the synchronization safety fixes on this branch. For release history, see [CHANGELOG.md](CHANGELOG.md).

## Features

- Dolibarr → CalDAV, CalDAV → Dolibarr, and bidirectional synchronization.
- Manual synchronization and automatic synchronization through Dolibarr scheduled jobs.
- Multiple connections and calendars, with display names, colors and user visibility.
- All-day events and timed events with timezone handling.
- Mapping of native agenda events to remote UIDs and ETags.
- Conflict detection that preserves both copies when both sides change.
- Native agenda display options: hide automatic system events and color event tiles by assigned user.
- French, German and English module translations, including connection help.

Automatic system events, such as invoice and proposal activity logs, are excluded from export. Hiding them in the agenda is a separate display option.

## Requirements

The module declares Dolibarr 16+ and PHP 7.4+. The synchronization fixes were checked with Dolibarr 24, PHP 8.5 and MariaDB; this does not establish compatibility with every older supported version.

Required PHP extensions include cURL, SimpleXML and mbstring. The Dolibarr server must be able to reach the CalDAV server over HTTPS. Automatic polling also requires a working scheduled-job runner.

## Installation and upgrades

1. Back up the Dolibarr database and existing module files.
2. Install the module as `htdocs/custom/caldavclient`, using the path appropriate to your installation. Alternatively, upload a module ZIP through **Setup → Modules/Applications → Deploy an external module**.
3. Give the web-server user read access to the module files and write access to the module's document/cache directory.
4. Enable **CalDAV Client** in **Setup → Modules/Applications**.
5. Configure and check the intended calendar before enabling automatic synchronization.

For an existing installation, disable automatic synchronization before replacing files. The module activation/upgrade path applies its database schema fixes, including the nullable `local_sync_hash` column in the event mapping table. Back up existing settings before deactivating and reactivating the module, and verify them afterward. Do not start synchronization until the schema update succeeds.

Reverting module files does not undo calendar writes or restore deleted events. Keep a backup of important calendars as well as Dolibarr data.

## Connection setup

Open **CalDAV Client → Configuration** and create a connection:

| Field | Value |
| --- | --- |
| Name | A descriptive connection name |
| URL | The provider's HTTPS server address |
| Username | The provider's account name |
| Password | The account or application password required by the provider |
| Calendar path | The provider's calendar path; leave empty only if the default/discovery path is appropriate |
| Verify SSL certificate | Keep enabled for production |
| Enabled | Enable the connection when ready |

The calendar path is appended to the server address. Use the exact path supplied by the provider and avoid repeating it in both fields. HTTP URLs are rejected. Disable certificate verification only for an isolated test server using a self-signed certificate.

For Nextcloud, a calendar path commonly has this form:

```text
URL: https://nextcloud.example.com
Calendar path: /remote.php/dav/calendars/USERNAME/CALENDAR/
```

Use an application password where the provider requires one. Never include real passwords in screenshots, issue reports or committed configuration.

### Google Calendar

Provider names alone do not guarantee compatible authentication. This module uses username/password authentication; it does not implement Google's OAuth authorization flow.

During testing, Google's current `apidata.googleusercontent.com/caldav/v2/` endpoint returned HTTP 401 with application-password authentication. The older endpoint worked with an application password for the selected calendar:

```text
URL: https://www.google.com
Calendar path: /calendar/dav/URL_ENCODED_CALENDAR_ID/events/
```

The calendar ID is available in Google Calendar settings under **Integrate calendar**. Encode the ID as a URL path component and check that the discovered calendar is the intended one, especially when using a shared or secondary calendar.

This older Google endpoint is deprecated and may stop working. Its observed success is not a guarantee for other accounts or future availability. The module does not automatically rewrite Google URLs.

## Discover and select calendars

1. Open **View calendars** for the connection.
2. Select **Discover calendars**.
3. Check the returned names and calendar URLs before enabling synchronization.
4. Edit each calendar's display name, color, enabled state and default agenda visibility.
5. Set the intended default export target and, where needed, its Dolibarr user/organizer.

**Public** visibility means visible to users in the Dolibarr entity; it does not publish the calendar on the internet. **Individual** visibility limits the calendar display to selected users.

## Synchronization

Choose a mode under **CalDAV Client → Synchronization**:

| Mode | Behavior |
| --- | --- |
| Dolibarr → CalDAV | Exports native manual agenda events to the selected remote calendar. |
| CalDAV → Dolibarr | Imports remote events into the native agenda and reconciles remote deletions. |
| Bidirectional | Compares both copies with their last successful synchronization state. |

In bidirectional mode:

- Unchanged copies cause no event writes.
- A local-only change is exported using the saved remote ETag.
- A remote-only change is imported after checking the current native event under a database row lock.
- Changes on both sides produce a conflict and preserve both copies.
- A deletion is propagated only when the remaining copy has not changed since the baseline.
- Existing mappings without a local baseline are initialized only when both copies match; divergent copies require manual review.

Do not treat this as “last change wins.” Review conflicts in the logs and compare both copies before resolving them. Do not delete mapping rows to force synchronization: this removes the baseline used for safe decisions.

Complete, valid remote inventories are required for deletion reconciliation. HTTP errors, malformed or partial responses stop reconciliation instead of being interpreted as an empty calendar. Writes use conditional requests, and deterministic initial export UIDs allow recovery after mapping persistence fails.

Native inbound changes and their mappings are transactional. They suppress outbound triggers to prevent loops. Per-calendar database locks prevent overlapping synchronization runs.

### Automatic synchronization

Enable **Automatic synchronization** in the module and configure the module job under **Tools → Scheduled jobs**. Ensure the Dolibarr scheduler is actually invoked by your deployment's cron runner. Saving an interval in the module does not create an operating-system scheduler.

Check the actual job frequency and enabled state. Before activating a global scheduler, inspect other enabled Dolibarr jobs, since it can also execute unrelated work. Use **Synchronize now** for the initial controlled check, then verify the automatic runner separately.

### Dates and supported edits

All-day iCalendar dates retain their calendar days; `DTEND` is exclusive. Native inclusive all-day ends are converted accordingly. Timed events retain their instants across timezone conversion.

Unsupported recurring events and edits involving attendees are stopped where they cannot be handled safely. Invalid dates and unsupported duration-based event definitions are rejected instead of silently changed. Remote updates retain unknown properties, timezone components and alarms rather than rebuilding the entire resource.

## Agenda and widget

Open **Agenda** and use the calendar checkboxes to show or hide external calendars. Displayed external events may be read-only overlays; synchronized native agenda events are stored in Dolibarr and follow the selected synchronization mode.

The home-page widget **Upcoming CalDAV events** shows events for today and tomorrow. Add it through the dashboard's widget settings. Calendar colors, dates, times and event details help distinguish sources.

Display reads can use a ten-minute cache. Synchronization bypasses this cache and requests a fresh complete inventory; display refresh and synchronization are separate operations.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| HTTP 401 | Account, application password and endpoint authentication requirements. A valid password does not make an OAuth-only endpoint accept Basic authentication. |
| HTTP 405 or no calendars | Server URL, calendar path and provider discovery support. |
| SSL failure | Certificate validity, hostname and the server's trust chain. |
| Events absent from the agenda | Connection/calendar enabled state, visibility permissions and selected calendar checkboxes. |
| Automatic sync does not run | Module setting, job enabled state, actual frequency and external scheduler runner. |
| Conflict | Both copies, their mapping baseline and the synchronization logs. |
| HTTP 412 | The remote copy changed; fetch current state before retrying. |
| Sync already running | Another run holds the calendar lock; avoid starting overlapping jobs. |

Enable Dolibarr debug logging briefly, reproduce the issue and inspect entries containing `CalDAV`. Treat logs as private because they may contain calendar URLs and event details. Redact credentials and personal information before sharing them.

## Development and validation

| Path | Responsibility |
| --- | --- |
| `admin/` | Connection, calendar and synchronization administration |
| `class/caldavclient.class.php` | Connection model and CalDAV client |
| `lib/caldav/` | HTTP-related calendar operations, fetching and iCalendar handling |
| `class/caldavsync.class.php`, `class/caldavsync/` | Native event synchronization and conflict handling |
| `class/caldaveventmapping.class.php` | UID, ETag and local baseline persistence |
| `class/agenda/` | Agenda display integration |
| `core/modules/`, `core/triggers/` | Module registration, schema upgrade and native event hooks |
| `sql/` | Connection, calendar and event mapping schemas |
| `langs/` | French, German and English translations |

Run the isolated contract checks without real calendar credentials:

```sh
php tests/dates.php
php tests/conflicts.php
php tests/sync-safety.php
```

`tests/native.php` additionally checks schema upgrades, native date roundtrips and transactional rollback against a disposable Dolibarr database. It is guarded for the dedicated test runtime named in that file. Never point it at a production database. These checks do not establish browser acceptance or compatibility with every CalDAV provider.

## License and support

Copyright (C) 2025–2026 MATER Stéphane.

Licensed under **GNU General Public License version 3 or later** (`GPL-3.0-or-later`). See [LICENSE](LICENSE) and [COPYING](COPYING).

Report bugs or contribute through [the GitHub repository](https://github.com/daryl40000/caldavclient). Include the Dolibarr/PHP/module versions, synchronization mode, reproduction steps and redacted logs. See the [French README](README.md) and [changelog](CHANGELOG.md) for further background.
