# Requirements Checklist (○=done / △=partial / ×=todo)

## Sync & Inventory
- [ ] iCal 2-way (UID/SEQUENCE/DTSTAMP/Cancel) …… ×
- [ ] Buffer/Blackout/Check-in DOW rules ………… ×
- [ ] Channel Provider Adapter skeleton ………… ×

## Pricing & Rules
- [ ] RateResolver (Season/DOW/LOS) ……………… ×
- [ ] Taxes/Fees calculator (unit/day/person) …… ×
- [ ] Coupons (flat/%) w/ conditions ……………… ×

## Booking & Payment
- [ ] Multi-room cart …………………………………… ×
- [ ] Booking state machine + Ledger ……………… ×
- [ ] Stripe (auth/deposit/capture/refund) ……… ×

## Portal
- [ ] Owner roles & capabilities …………………… ×
- [ ] Stripe subscription for listing ……………… △
- [ ] Official-site template link …………………… △

## UI/UX
- [ ] Availability calendar (per property) ……… △
- [ ] Quote breakdown (tax/fees) …………………… ×

## API & Ops
- [ ] REST read-only (availability/quote) ……… ×
- [ ] Webhook (booking/payment) …………………… ×
- [ ] i18n policy (.po/.mo, key naming) ………… △
