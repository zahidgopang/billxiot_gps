# SaaS billing & ERP architecture

GPS fleet tracking stays on Traccar (`tc_*`). Billing, plans, invoices, and profit reporting use Laravel tables.

## Data model

| Table | Purpose |
|-------|---------|
| `subscription_plans` | Catalog: name, duration, **company_price**, features, public flag |
| `subscriptions` | Device subscription + `company_price`, `selling_price`, device costs, invoice FKs |
| `billing_invoices` | `platform` (vendor → client) or `client` (client → end user) |
| `billing_invoice_lines` | Subscription and/or device lines with cost vs price |
| `billing_payments` | Partial/full payments; status derived from balance |

## Invoice types

**Platform invoice (`PINV-*`)** — Admin/Super Admin bills the client:

- Subscription **company price** only (plan amount)
- Device hardware cost is **excluded** (client already bought stock separately)

**Client invoice (`CINV-*`)** — Client bills the end user:

- Subscription **selling price**
- Device **selling price** (optional line)
- Total = subscription + device

## Subscription create flow

1. Select client → device → **plan** (company price auto-fills)
2. Enter **end-user selling price** → profit margin calculated in UI
3. Device **purchase cost** loaded from client FIFO / last sale
4. Enter **device selling price** → device profit shown
5. On save: both invoices created; if status is `active`, stock consumed (or skipped if already installed)

## Cancellation

- Invoices marked **cancelled**
- Inventory **return** movement if an install movement exists for the device

## Profit & loss report

Scoped by role (`ProfitLossReportService`):

- **Super Admin** — all clients, warehouse stock
- **Admin** — assigned clients only
- **Client** — own company only

Metrics: subscription/device profit, revenue, pending dues, invoice counts, subscription counts, available stock units.

## Permissions (`config/rbac.php`)

- `billing.view` — invoices + P&L (admin + client panel)
- `billing.manage` — plans CRUD + record payments (admin only)

## Public pricing

`GET /pricing` loads active public plans from `subscription_plans`.

## Inventory integration

Uses existing `InventoryService` FIFO ledger:

- `consumeForInstall` on active subscription (if not already consumed at device install)
- `returnFromSubscriptionCancel` on subscription cancel/delete

## Next phases (optional)

- Payment gateway webhooks (Stripe, etc.)
- Automated overdue cron (`BillingInvoiceService::markOverdueInvoices`)
- Renewals that regenerate invoices
- Tax lines and multi-currency
- Serial/IMEI-level unit tracking

```bash
php artisan migrate
```
