# Architecture Overview

## Modules
- Sync: iCal Import/Export + Reconciliation
- Rules: Min/Max nights, Check-in/out DOW, Buffer, Blackout
- Rates: Season, DayOfWeek, LengthOfStay, LastMinute
- Payments: Stripe (auth/deposit/capture/refund)
- Portal: Owner/Manager roles, listing status (active/warning/suspended)
- Providers: Channel/* (future), Payment/* (Stripe first)
- API: REST (read-first), Webhooks (booking/payment)
- i18n: WPML-first policy

## Key Interfaces (PHP)
- Contracts/Rules/RuleInterface::applies|validate|priority
- Services/Rules/RuleEngine (Pipeline)
- Contracts/Rates/ResolverInterface
- Services/Rates/RateResolver
- Providers/Channel/AbstractChannelProvider
- Sync/IcalImporter, Sync/IcalExporter (UID/SEQUENCE/DTSTAMP/CANCEL)

## Overwrite Order
Global < PropertyType < Property

## Data Hints
- Availability bitmap (property_id, yyyymmdd, bits)
- Booking ledger (event-sourced)
- Owner accounts (stripe_customer_id, plan, status)
