# WhatsApp pilot setup — Pacto de Amor

## Current state

Only configuration, a secret-safe diagnostic command, and a personal opt-in preference are implemented. The GET webhook verification handshake is implemented at `/api/integrations/whatsapp/webhook`. Incoming message processing, phone enrollment, AI calls, event creation over chat, and visitor-query chat are not connected yet. Do not subscribe to message deliveries until the POST handler is implemented. `WHATSAPP_ENABLED=false` by default. Do not enable it as a substitute for completing and testing those workflows.

## Credentials

1. In https://platform.openai.com/api-keys create an application API key in a project for this system. Confirm billing/credits in the project. Store the key in the backend `.env` as `OPENAI_API_KEY`; never in frontend/VITE settings, Git, or chat. Leave `OPENAI_MODEL` empty until the cost-efficient tool-capable model has been selected and tested.
2. Open https://developers.facebook.com/apps/ and create an app with the WhatsApp business messaging use case. Start with Meta's test sender before registering the dedicated church number.
3. From the WhatsApp API setup collect the Phone Number ID, WhatsApp Business Account ID, access token, and API version. From the app settings collect the App Secret. Store these under their corresponding `WHATSAPP_` keys in backend `.env`. Temporary test tokens are for development; production needs an appropriate system-user access token.
4. `WHATSAPP_VERIFY_TOKEN` will be a random local secret used for webhook setup, not the access token. A local token has been generated for the callback; copy its value directly from `.env` into Meta when configuring the webhook.
5. Set `WHATSAPP_CHURCH_ID` to Pacto de Amor's actual database ID, after checking it. Do not guess the ID.

Run `php artisan config:clear` then `php artisan whatsapp:status`. The command reports presence only, never credential values, and makes no paid API requests.

## Remaining before live activation

- Signed HTTPS webhook, duplicate-delivery protection, private queue storage, and outbound delivery.
- Bind each verified WhatsApp sender to an existing user in this church. Never trust a name, claimed role, or department typed into chat.
- Gate every inbound task and outbound job on the global switch, church match, personal opt-in, current verified binding, and current module/action permissions. Check opt-out before invoking AI; drop pending work after opt-out.
- Application-enforced usage limits and spending controls. Provider billing alerts alone are not a hard cap.
- Reuse event conflict, reservation, objective, review, and resubmission rules, with explicit submission confirmation and deduplication.
- Pastor visitor queries with date clarification, scoped access, and authenticated report links.
- Test with Meta's test sender and fictitious records before deploying to the dedicated number.

Personal preference is at `/whatsapp-settings`. It belongs to the logged-in account even in a superadmin drilldown. Saving it does not grant module permissions or enroll a phone number.
