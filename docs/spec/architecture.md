# Architecture Addendum (v0.4.1): Embed + WP Connector + Domain Mapping

## Objectives
- Owners can keep any official website (WordPress or other CMS) while **all inventory/price/booking is centralized** in Minpaku Suite (the portal).
- Provide two integration modes:
  - **B: WP Connector** — A WordPress plugin that renders availability/quote UI via portal APIs. Checkout is handled by the portal (hosted).
  - **C: White-label Sites** — Mini-sites hosted by the portal with optional **custom domain mapping** (CNAME). Zero setup for owners.

## High-level Components
1) **Portal Core (Minpaku Suite)**
   - Multi-tenant (Owner, Property)
   - Booking, Pricing/Rules, iCal sync, Payments, Webhooks
   - Public APIs: /v1/availability, /v1/quote, /v1/bookings
   - Embed assets: mbed.js, widget.html
2) **WP Connector (External WP)**
   - Shortcodes/Blocks: [minpaku_availability property_id="..."]
   - Admin settings: API Key + HMAC Secret (stored in options, non-export)
   - Renders calendar & quote using Portal APIs; checkout opens hosted portal flow (modal or redirect)
3) **White-label Site**
   - Route: https://{tenant-sub}.portal.example.com/stay/{property-slug}
   - Optional **Custom Domain**: CNAME stay.owner.com -> {tenant-sub}.portal.example.com
   - Theming via sections (Hero/Gallery/Calendar/Quote)
4) **Security**
   - HMAC-signed POST (bookings), reCAPTCHA, IP + Owner + Property Rate-limit
   - CORS: GET allowed w/ cache; POST only from trusted origins (widget host / same-site)
   - PII & payments strictly on portal; connector never stores payment tokens
5) **Observability**
   - Widget events (view, date_select, begin_checkout, purchase) -> dataLayer hooks
   - Webhooks: ooking.created, payment.captured, ooking.cancelled

## Data Flows
- **Widget (B/C)** → GET /v1/availability?property_id&from&to (cacheable)
- Quote: POST /v1/quote ({ checkin, checkout, guests, coupon? })
- Booking: POST /v1/bookings (HMAC) → Payment → Webhook → Booking confirmed
- Invalidation: booking change / iCal import / rule update → purge cached availability

## Domain Mapping (White-label)
- Owner chooses stay.owner.com
- DNS: CNAME stay.owner.com -> wl.portal.example.com
- Validation: ACME/HTTP or TXT; validate + issue TLS cert
- Traffic: CDN (edge) → portal → white-label renderer

## Minimal API Surface (v1)
- GET /v1/availability → 200 [{date, status: available|partial|blocked|blackout, min_stay?}]
- POST /v1/quote → 200 { totals, taxes, fees, policy, hold_expires_at }
- POST /v1/bookings (HMAC) → 201 { booking_id, payment_client_secret? }
- Headers: X-Minpaku-Cache: HIT|MISS, X-RateLimit-*

## Versioning / Contracts
- Embed widget version in URL: /embed/v1/embed.js
- Connector plugin declares X-Minpaku-Connector: 1.x
- Breaking changes via /v2/* with dual-serve window

## Acceptance (Architecture)
- External WP can render calendar/quote and complete booking via portal.
- White-label site renders same property with consistent availability/pricing.
- Booking consistency: one source of truth (portal DB), webhooks emitted.
