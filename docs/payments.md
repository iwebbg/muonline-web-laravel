# Credits payments configuration

MuOnline Web sells administrator-defined Credits packages through hosted
checkout pages from Stripe, PayPal or Revolut. Payment credentials remain on
the server, every purchase keeps an immutable package snapshot, and Credits are
added only after the provider result has passed signature, amount and currency
verification.

Configure and test one provider at a time. A provider is shown to players only
when its `*_ENABLED` value is `true` and every credential required by that
provider is present.

## Before configuring a provider

The application must have a public HTTPS address before a provider can deliver
webhooks. Set the canonical URL in the root `.env`:

```dotenv
APP_URL=https://mu.example.com
PAYMENTS_CURRENCY=EUR
```

`APP_URL` is used to generate checkout return and webhook URLs. Do not add a
trailing slash. `PAYMENTS_CURRENCY` is the default currency shown when an
administrator creates a package; each saved package retains its own three-letter
ISO currency code and price.

Apply application migrations before creating packages:

```bash
./docker/compose exec -T app php artisan migrate --force --no-interaction
```

Create and publish packages from **Admin → Economy → Credits packages**. Draft
packages are never offered to players. The account checkout is available under
**Account → Credits**.

After changing `.env`, clear Laravel's cached configuration and recreate the
containers so Compose reads the new environment values:

```bash
./docker/compose exec -T app php artisan optimize:clear
./docker/compose up --detach --force-recreate
```

## Creating webhook signing secrets

Stripe and Revolut use a webhook signing secret that is separate from the API
secret key. MuOnline Web can create the provider endpoint and print the value
that belongs in `.env`:

```bash
./docker/compose exec -T app php artisan payments:webhook:register stripe
./docker/compose exec -T app php artisan payments:webhook:register revolut
```

For an installation without Docker, run the same Artisan command directly:

```bash
php artisan payments:webhook:register stripe
php artisan payments:webhook:register revolut
```

The command uses `APP_URL` to build the provider-specific public HTTPS URL. An
explicit address can be supplied when the public reverse-proxy origin differs:

```bash
php artisan payments:webhook:register stripe \
  --url=https://mu.example.com/payments/webhooks/stripe
```

Before running it, configure the selected provider API secret and mode, then
clear cached configuration. The command prints either
`STRIPE_WEBHOOK_SECRET=whsec_...` or
`REVOLUT_WEBHOOK_SECRET=wsk_...`. Copy that complete line into the root `.env`,
clear cached configuration again, and only then set the provider's
`*_ENABLED=true` value.

The secret is deliberately not written to `.env` automatically and must never
be copied into source control, shell scripts, screenshots or deployment logs.
Each command execution creates a new provider endpoint. Do not rerun it merely
to inspect an existing registration; remove obsolete endpoints in the provider
account after a deliberate rotation.

## Stripe

MuOnline Web uses server-created Stripe Checkout Sessions. It needs a server
secret key and a separate signing secret for the registered webhook endpoint;
it does not need a publishable key.

1. Open the Stripe Dashboard and select a Sandbox while testing.
2. Open **Developers → API keys** and copy the sandbox secret key beginning
   with `sk_test_` into `.env`, leaving Stripe disabled initially:

   ```dotenv
   STRIPE_ENABLED=false
   STRIPE_SECRET_KEY=sk_test_replace_me
   STRIPE_WEBHOOK_SECRET=
   ```

3. Clear cached configuration and run the registration command:

   ```bash
   ./docker/compose exec -T app php artisan optimize:clear
   ./docker/compose exec -T app php artisan payments:webhook:register stripe
   ```

   It registers this endpoint:

   ```text
   https://mu.example.com/payments/webhooks/stripe
   ```

   with the events consumed by the application:

   - `checkout.session.completed`
   - `charge.refunded`

4. Copy the printed `STRIPE_WEBHOOK_SECRET=whsec_...` line into `.env`, enable
   Stripe, and clear cached configuration again:

   ```dotenv
   STRIPE_ENABLED=true
   STRIPE_SECRET_KEY=sk_test_replace_me
   STRIPE_WEBHOOK_SECRET=whsec_replace_me
   ```

The current checkout credits a synchronous payment when the completed Checkout
Session reports `payment_status=paid`. Do not enable delayed payment methods
until support for Stripe's asynchronous success and failure events is added.

For production, switch the Stripe Dashboard to live mode, create a separate
live webhook endpoint, replace the key with its `sk_live_` value and replace
`STRIPE_WEBHOOK_SECRET` with the live endpoint's signing secret. Sandbox and
live webhook secrets are different.

For local-only webhook testing, Stripe CLI can forward snapshot events and
prints a temporary listener signing secret:

```bash
stripe listen \
  --events checkout.session.completed,charge.refunded \
  --forward-to http://localhost/payments/webhooks/stripe
```

Use the printed `whsec_...` value while that listener is running.
`stripe listen` is a development transport, not a production webhook
registration. Do not add Stripe CLI as a permanent application container or
service. On a public VPS, Stripe calls the registered HTTPS endpoint directly;
the registration command above creates the permanent endpoint and secret.

## PayPal

PayPal credentials and webhooks belong to a REST API application. Sandbox and
live applications have separate credentials and webhook IDs.

1. Open the PayPal Developer Dashboard and select **Apps & Credentials**.
2. Keep **Sandbox** selected, create a Merchant REST API app, and associate it
   with a sandbox business account.
3. Copy the app's **Client ID** and **Client secret**.
4. In the app's **Sandbox Webhooks** section, add:

   ```text
   https://mu.example.com/payments/webhooks/paypal
   ```

5. Subscribe to these events:

   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.REFUNDED`
   - `PAYMENT.CAPTURE.REVERSED`

6. Save the webhook and copy its **Webhook ID**. This is an identifier used by
   PayPal's signature verification API; it is not the client secret.
7. Configure the root `.env`:

   ```dotenv
   PAYPAL_ENABLED=true
   PAYPAL_MODE=sandbox
   PAYPAL_CLIENT_ID=replace_with_sandbox_client_id
   PAYPAL_SECRET=replace_with_sandbox_client_secret
   PAYPAL_WEBHOOK_ID=replace_with_sandbox_webhook_id
   ```

Use the sandbox personal account as the buyer and the sandbox business account
as the seller when testing the complete redirect, capture and webhook flow.

For production, select **Live** in Apps & Credentials, use a verified PayPal
Business account, obtain the live Client ID and secret, and register the same
webhook URL under the live application. Then set:

```dotenv
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=replace_with_live_client_id
PAYPAL_SECRET=replace_with_live_client_secret
PAYPAL_WEBHOOK_ID=replace_with_live_webhook_id
```

Do not reuse a sandbox webhook ID with live credentials.

## Revolut Pay

MuOnline Web uses the Revolut Merchant API and its hosted checkout page. It
needs the server-side Secret API key and the signing secret created for the
webhook. No Revolut public key is required by this hosted flow.

1. Create a dedicated Revolut Business Sandbox account and open its Merchant
   account.
2. From the Merchant overview, open **Merchant API**, generate a Sandbox Secret
   API key, and copy it into `.env`, leaving Revolut disabled initially:

   ```dotenv
   REVOLUT_ENABLED=false
   REVOLUT_MODE=sandbox
   REVOLUT_SECRET_KEY=replace_with_sandbox_secret_api_key
   REVOLUT_WEBHOOK_SECRET=
   REVOLUT_API_VERSION=2026-08-17
   ```

3. Clear cached configuration and create the Merchant API webhook:

   ```bash
   ./docker/compose exec -T app php artisan optimize:clear
   ./docker/compose exec -T app php artisan payments:webhook:register revolut
   ```

   The command registers this URL and event:

   ```text
   URL: https://mu.example.com/payments/webhooks/revolut
   Event: ORDER_COMPLETED
   ```

4. Copy the printed `REVOLUT_WEBHOOK_SECRET=wsk_...` line into `.env`, enable
   Revolut, and clear cached configuration again:

   ```dotenv
   REVOLUT_ENABLED=true
   REVOLUT_MODE=sandbox
   REVOLUT_SECRET_KEY=replace_with_sandbox_secret_api_key
   REVOLUT_WEBHOOK_SECRET=wsk_replace_me
   REVOLUT_API_VERSION=2026-08-17
   ```

The Revolut Business API Keys screen displays the Secret and Public API keys,
not this webhook signing secret. The signing secret is returned by the Merchant
API when the webhook is created. MuOnline Web does not need the public key for
its hosted checkout flow.

`REVOLUT_API_VERSION` is the Merchant API contract version used by the current
integration. Do not change it merely to the newest published version; upgrade
it only together with a compatibility review and payment tests.

For production, obtain approval for a Revolut Merchant account, generate a
Production Secret API key, and create a separate production webhook. Replace
the credentials and set:

```dotenv
REVOLUT_MODE=live
REVOLUT_SECRET_KEY=replace_with_production_secret_api_key
REVOLUT_WEBHOOK_SECRET=wsk_replace_with_production_webhook_secret
```

Revolut Sandbox and Production are isolated environments. Their accounts,
orders, keys and webhook secrets are not interchangeable.

## Complete `.env` reference

Start with every provider disabled, then enable only the providers that have
been configured and tested:

```dotenv
PAYMENTS_CURRENCY=EUR

STRIPE_ENABLED=false
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=

PAYPAL_ENABLED=false
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_WEBHOOK_ID=

REVOLUT_ENABLED=false
REVOLUT_MODE=sandbox
REVOLUT_SECRET_KEY=
REVOLUT_WEBHOOK_SECRET=
REVOLUT_API_VERSION=2026-08-17
```

| Variable | Required when | Purpose |
| --- | --- | --- |
| `PAYMENTS_CURRENCY` | Always | Default currency for newly created admin packages. |
| `STRIPE_ENABLED` | Stripe | Makes Stripe available after all Stripe secrets are present. |
| `STRIPE_SECRET_KEY` | Stripe | Server API key used to create and inspect Checkout Sessions. |
| `STRIPE_WEBHOOK_SECRET` | Stripe | `whsec_` secret for this exact Stripe webhook endpoint and environment. |
| `PAYPAL_ENABLED` | PayPal | Makes PayPal available after all PayPal values are present. |
| `PAYPAL_MODE` | PayPal | `sandbox` or `live`; selects the matching PayPal API host. |
| `PAYPAL_CLIENT_ID` | PayPal | REST API application Client ID for the selected environment. |
| `PAYPAL_SECRET` | PayPal | REST API application client secret. |
| `PAYPAL_WEBHOOK_ID` | PayPal | ID of the webhook registered under the same REST API application. |
| `REVOLUT_ENABLED` | Revolut | Makes Revolut available after all Revolut secrets are present. |
| `REVOLUT_MODE` | Revolut | `sandbox` or `live`; selects the matching Merchant API host. |
| `REVOLUT_SECRET_KEY` | Revolut | Server-side Merchant Secret API key. |
| `REVOLUT_WEBHOOK_SECRET` | Revolut | Signing secret of the registered Merchant webhook. |
| `REVOLUT_API_VERSION` | Revolut | Pinned Merchant API request version expected by this integration. |

The optional `STRIPE_BASE_URL` setting exists for automated integration tests
and controlled provider proxies. Normal installations should leave it unset so
the official Stripe API URL is used.

## Go-live checklist

Before enabling real payments:

1. Confirm `APP_URL` is the final public HTTPS origin.
2. Confirm the package price and currency in Admin match the intended charge.
3. Complete one successful sandbox checkout for every enabled provider.
4. Confirm the Credits wallet changes once and only once.
5. Resend the successful webhook and confirm Credits are not duplicated.
6. Test cancellation and an invalid webhook signature.
7. Test a full refund and review the payment status. Refund notifications are
   recorded, but the current policy does not automatically subtract Credits
   already spent by the player; handle that case administratively.
8. Create separate live credentials and live webhook registrations.
9. Replace only the relevant `.env` values, clear cached configuration and
   restart the application containers.
10. Make a low-value live purchase and reconcile it in both the provider
    dashboard and **Admin → Economy → Credits packages**.

Never commit `.env`, API keys, client secrets or webhook secrets. Rotate a
credential immediately if it is exposed, and update the application and the
provider webhook configuration as one operation.

## Official provider references

- [Stripe API keys](https://docs.stripe.com/keys)
- [Stripe webhook endpoints](https://docs.stripe.com/webhooks)
- [PayPal REST API credentials](https://developer.paypal.com/api/get-started/)
- [PayPal production setup](https://developer.paypal.com/api/rest/production/)
- [Revolut Merchant API setup](https://developer.revolut.com/docs/guides/merchant/get-started)
- [Revolut Sandbox](https://developer.revolut.com/docs/guides/merchant/test-and-go-live/set-up-sandbox)
- [Revolut webhook signatures](https://developer.revolut.com/docs/guides/merchant/monitor-and-observe/webhooks/verify-the-payload-signature)
