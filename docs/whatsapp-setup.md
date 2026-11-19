# WhatsApp pilot — Pacto de Amor

## Implemented, disabled by default

- Signed GET verification and POST text-message ingestion at `/api/integrations/whatsapp/webhook`.
- One configured church/Meta sender, authenticated personal opt-in, single-use ten-minute linking codes, unlinking, and immediate `BAJA` / `STOP` opt-out without AI.
- Encrypted sender, draft, and inbox text. Queue payloads contain database IDs only. Webhook content is redacted from ordinary audit logs.
- Provider-message deduplication; account-specific processing locks; generation checks invalidate work after opt-out/unlink. Jobs older than ten minutes are discarded.
- Bounded Responses API extraction (`store=false`, maximum 16,000 input bytes and 700 output tokens). AI proposes fields only; no model-generated SQL, permission decisions, approvals, or confirmations.
- Temporary event holds, four-minute warning, `EXTENDER` for another five minutes, exact `CONFIRMAR` to submit, `CANCELAR`, `OBJETIVOS`, `ESTADO`, and `EDITAR <event id>` followed by confirmation to resubmit the same changes-requested event. Existing event controller/policies enforce conflicts, objectives, status transitions, and capability rules. Clerk conference rules are unchanged; conference authoring remains in the app.
- Visitor counts scoped to the user's church and current visitors module permission, with authenticated date-filtered links. Names/contact information are not sent to AI or WhatsApp. Date filters use UTC and survive login.
- Default hard monthly request limits: 500 AI requests, 1,000 outgoing replies, 2,000 accepted incoming messages; 30 operations of each type per user per UTC day. Set limits to zero to stop those operations. Failed/ambiguous provider attempts count. These are request/token caps, **not a guaranteed dollar spending ceiling**; provider prices, taxes, and separately configured services still apply.

## Pilot limitations and verification

Automated tests use fake Meta/OpenAI responses; they do not establish that your credentials or chosen model work. Browser verification covered the disabled settings page. Complete a real Meta test-number smoke test before live use.

Text and phone-number-addressed senders only in this first pilot; voice, attachments, username-only/BSUID senders, proactive templates, and bulk messaging are not supported. Commands are Spanish and replies currently default to Spanish. Dates must resolve to an explicit timezone offset before a hold is created. Visitors use UTC dates.

No automatic network retries: a timeout may mean the remote system accepted a request. A message claimed by a crashed worker is not replayed automatically, to avoid duplicate submissions/cost. Use `ESTADO` or the app to verify an event before trying again. Inbox status `reply_failed` means the operation may have completed but its reply was not delivered. `processing` left after a crash requires inspection, not blind replay. Once quotas are exhausted the assistant becomes silent; use the app. Hold warnings require a timely worker and remaining reply quota; reservations still expire without a delivered warning.

## Credentials

Backend `.env` only; never VITE variables, Git, or chat:

```
WHATSAPP_ENABLED=false
WHATSAPP_CHURCH_ID=<actual Pacto de Amor database ID>
WHATSAPP_PHONE_NUMBER_ID=<Meta test sender phone number ID>
WHATSAPP_BUSINESS_ACCOUNT_ID=<Meta WABA ID>
WHATSAPP_ACCESS_TOKEN=<private access token>
WHATSAPP_APP_SECRET=<private Meta app secret>
WHATSAPP_VERIFY_TOKEN=<random private verification token>
WHATSAPP_GRAPH_VERSION=<supported version shown in Meta setup, e.g. vNN.0>
OPENAI_API_KEY=<private application project key>
OPENAI_MODEL=<evaluated model supporting Responses structured outputs>
WHATSAPP_MONTHLY_AI_LIMIT=500
WHATSAPP_MONTHLY_REPLIES_LIMIT=1000
WHATSAPP_MONTHLY_INBOX_LIMIT=2000
WHATSAPP_DAILY_USER_LIMIT=30
```

Create credentials through https://platform.openai.com/api-keys and https://developers.facebook.com/apps/. Start with Meta's test sender and registered test recipients. Revoke any token previously exposed in screenshots. Production needs an appropriate long-lived system-user token, not a short-lived developer token.

Keep `APP_KEY` stable: it encrypts stored WhatsApp contents and keys sender hashes. Set `APP_URL=https://churchadmin.adminmyclub.com` on the server. Set `APP_DEBUG=false`. Keep the existing database connection; do not copy the entire local `.env` over the server's file.

`php artisan whatsapp:status` checks configuration presence only and makes no provider calls.

## Mac server deployment preparation

Known server: `/var/www/churchAdmin`, Homebrew Nginx / PHP 8.4 / PostgreSQL 16, cloudflared already running. No need to start another tunnel on this laptop.

1. Commit/push the tested release first; do not pull an unspecified release. Back up the deployment database, `.env`, and private storage using your existing backup process.
2. Preserve the server's changed `public/spa` build before a fast-forward pull (the inspected changes were confined to generated frontend assets):

   ```sh
   cd /var/www/churchAdmin
   git stash push --include-untracked -m 'Server frontend build before WhatsApp pilot' -- public/spa
   git pull --ff-only
   composer install --no-dev --prefer-dist --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan whatsapp:status
   ```

   Review any new server changes before stashing. Do not pop the old frontend build onto the new release. The repository includes built `public/spa` assets; no server npm build is required. Keep WhatsApp disabled during deployment.
3. Prepare service files with the **server's** PHP binary (the generator only writes plists):

   ```sh
   mkdir -p /tmp/churchadmin-services
   php deploy/macos/prepare-whatsapp-services.php /tmp/churchadmin-services
   plutil -lint /tmp/churchadmin-services/*.plist
   ```

4. Review generated paths. Install only if these labels do not already exist:

   ```sh
   mkdir -p "$HOME/Library/LaunchAgents"
   cp -n /tmp/churchadmin-services/*.plist "$HOME/Library/LaunchAgents/"
   launchctl bootstrap "gui/$(id -u)" "$HOME/Library/LaunchAgents/com.churchadmin.whatsapp.plist"
   launchctl bootstrap "gui/$(id -u)" "$HOME/Library/LaunchAgents/com.churchadmin.scheduler.plist"
   launchctl print "gui/$(id -u)/com.churchadmin.whatsapp"
   launchctl print "gui/$(id -u)/com.churchadmin.scheduler"
   ```

   These are user LaunchAgents: they require that user's logged-in GUI session. For unattended operation before login, configure system LaunchDaemons with the appropriate user instead. The worker is dedicated to `database --queue=whatsapp`; it does not consume existing default-queue jobs. Scheduler also runs the app's existing hold-expiry and agenda-closing tasks. Ensure neither scheduler nor queue worker is duplicated elsewhere. Existing default queues/Reverb, if used, need their own services.

5. Confirm the public domain serves the new release. In Meta set callback URL to `https://churchadmin.adminmyclub.com/api/integrations/whatsapp/webhook` and copy the server's verification token privately. Subscribe to `messages`. Verification works while disabled. No Cloudflare challenge or login page may intercept this specific webhook route; preserve signature checks.
6. Only after configuration and test-recipient setup, turn `WHATSAPP_ENABLED=true`, run `php artisan config:cache`, and restart the worker so it sees the new settings:

   ```sh
   php artisan queue:restart
   launchctl kickstart -k "gui/$(id -u)/com.churchadmin.scheduler"
   ```

   The launch agent restarts the queue worker after graceful exit. To stop immediately, stop its launch service as well as setting the global flag false. Requests already sent to providers cannot be recalled.
7. Sign in as the actual pilot user (not superadmin viewing context), open **Asistente WhatsApp**, enable personal opt-in, generate a code, and send the displayed `VINCULAR ...` text from that user's WhatsApp to Meta's test number. Reload settings to see the linked state. Never share the code.
8. Smoke test: ask for objectives; draft a fictitious event with explicit date/timezone; verify the hold in the app; extend; confirm once; verify pending review. Request changes in the clerk UI, `EDITAR <id>`, change details and confirm; verify the same event ID. Check conflicting time rejection, the four-minute warning, and expired confirmation rejection. Test pastor visitor dates, login link, and `BAJA`. Confirm usage caps using small temporary limits before enabling additional users.

## Retention and troubleshooting

`whatsapp:prune` runs daily via the scheduler: removes message contents after a day, deduplication records after thirty days, drafts after a day of inactivity, and expired link codes. Processed message bodies are cleared immediately. Usage counters contain no message content. Protect application/database backups, encryption keys, and service logs.

Check `storage/logs/com.churchadmin.whatsapp.log`, inbox status counts, and `php artisan queue:failed` without sharing private log contents. The app deliberately does not log provider response bodies. Test configuration failures locally with fake providers rather than repeatedly sending billable requests.

API references used: [OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [Meta incoming messages](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages), and [Meta message API](https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/message-api).


## Church controls and clerk-managed enrollment

Superadmin → Configuración selects a church and controls WhatsApp and OpenAI independently. Existing behavior is preserved on upgrade (church switches default on); the global WhatsApp environment switch, configured pilot church, user opt-in, and workflow permissions still apply. Enabling another church in this screen does not expand the single-number pilot or provision credentials. All provider credentials remain in the server environment.

The clerk or superadmin can set an E.164 WhatsApp number (for example `+12025550123`) and enable the assistant when creating or editing a user. The number remains pending until a signature-verified inbound message arrives from that exact number. The first message activates the assigned account and returns help without an AI call. Changing the number revokes the previous binding and pending work. Numbers are uniquely reserved across accounts. Disabling an account's assistant or a church blocks processing; the church switch also invalidates queued work. Manual code linking remains available. No WhatsApp invitations are sent automatically.

Monthly limits count reserved operations, including failed requests. Church limits cannot exceed environment caps. WhatsApp's global monthly caps still apply to all usage. Birthday AI has its own church-month and per-user-day caps. Periods are UTC. Disabling AI blocks WhatsApp AI parsing and birthday generation, while non-AI WhatsApp commands remain available if WhatsApp is enabled.

The usage dialog shows current limits and monthly request counts. New OpenAI responses that include `usage` contribute input/output token totals by church and feature. Token tracking starts with this release; historical or external provider usage is not reconstructed, and this is not a billing statement. Existing pilot global WhatsApp counters are copied to the configured church on migration. No access tokens or API secrets are exposed in this interface.

Deploy with `php artisan migrate --force`, then `php artisan queue:restart` so existing workers load the new checks.

### Provider usage reports

Superadmin → Configuración → Ver uso y límites now loads provider reports separately from the immediate church counters. Results (including errors) are cached for 30 minutes and refreshed while the modal remains open. Reports use UTC month boundaries, and never enforce limits based on delayed billing data.

Meta uses the existing WhatsApp access token, WABA and configured Graph API version to read pricing analytics. The token must have analytics access. Reported volume/cost covers the entire WABA, not just the selected church; cost is shown in the account's billing currency. Missing configuration, permissions and incomplete responses are displayed as unavailable, not as zero spend.

OpenAI organization usage and costs require an optional **server-only** `OPENAI_ADMIN_KEY`. The ordinary `OPENAI_API_KEY` is not used for these endpoints. Without the admin key, existing per-church response token tracking continues normally. Organization costs may include products beyond the model requests shown. Provider totals are shared-account totals and must not be attributed to individual churches. Never place either key in frontend configuration. After configuring a key, refresh Laravel's configuration cache.

Provider GET calls do not generate AI responses or send WhatsApp messages. No tokens, raw errors or provider pagination URLs are returned to browsers.
