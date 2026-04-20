# WHMCS Client Payment Terms by Client Group

A lightweight WHMCS hook that adds client-specific invoice payment terms such as:

- Immediate
- Net 30
- Net 60

It uses the client's **Client Group** to determine the payment terms and adjusts **only** the invoice due date during the `InvoiceCreation` hook.

## Why this exists

WHMCS does not provide a simple native way to separate:

1. the service renewal cycle,
2. the invoice creation timing,
3. and the invoice payment terms.

A common workaround is to generate invoices many days before the service due date. But that is not true Net 30 or Net 60. It is just billing earlier.

This hook preserves the normal WHMCS service renewal logic and changes only the invoice due date based on the client's group.

## Features

- Uses **Client Groups** as payment term selectors
- Changes **only** the invoice `duedate`
- Runs on **`InvoiceCreation`** so the invoice email can already contain the correct due date
- Preserves native WHMCS behavior when using the original WHMCS due date as the base date
- Works well with accounting sync flows such as QuickBooks, because the invoice can be updated before downstream hooks process it

## What it does not do

- Does **not** change the service **Next Due Date**
- Does **not** change renewal logic
- Does **not** change invoice totals
- Does **not** change line items
- Does **not** change suspension logic
- Does **not** add a native payment-terms field to the WHMCS client profile

## Why `InvoiceCreation`?

`InvoiceCreated` runs too late if you want the invoice email to go out with the corrected due date.

This hook uses `InvoiceCreation` so the due date is adjusted before the invoice is finalized and delivered.

## Recommended logic

The recommended mode is:

- use the **original WHMCS invoice due date** as the base date
- add 0, 30, or 60 extra days depending on the client group

This preserves the standard WHMCS behavior for clients without credit terms.

### Example

If WHMCS generates an invoice 7 days before the service renewal date:

- standard client with no mapped group → keeps the original WHMCS due date
- Net 30 client → original due date + 30 days
- Net 60 client → original due date + 60 days

## Installation

1. Copy the hook file into:

   `/includes/hooks/`

2. Edit the group mapping in the hook file:

```php
const Y2K_PAYMENT_TERMS_BY_GROUP_ID = [
    4 => 30,
    5 => 60,
];
```

Replace those IDs with the actual WHMCS client group IDs from your installation.

Any client with no group or an unmapped group defaults to standard WHMCS behavior.

3. Keep this option enabled unless you intentionally want a different behavior:

```php
const Y2K_USE_ORIGINAL_INVOICE_DUE_DATE_AS_BASE = true;
```

4. Test with at least:

- one client with no group
- one client in Net 30
- one client in Net 60

## Suggested WHMCS settings

A common starting point is:

- **Invoice Generation**: your preferred value (`0`, `1`, `7`, etc.)
- **Process Days Before Due**: `0`
- **Suspend Days**: according to your operational policy

## Important note about suspension

This hook only changes the **invoice due date**.

WHMCS suspension logic still depends on:

- the service **Next Due Date**
- the global **Suspend Days** setting

So this hook supports mixed invoice payment terms, but suspension timing remains global unless you customize that separately.

## File structure

```text
whmcs-client-payment-terms/
├── includes/
│   └── hooks/
│       └── client_payment_terms_invoicecreation.php
├── LICENSE
├── README.md
├── .gitignore
└── COMMUNITY-POST.md
```

## Logging

Optional Activity Log entries are included to help testing and troubleshooting.

## Example use case

This is useful if you want to:

- keep WHMCS recurring billing behavior unchanged
- offer selected clients Net 30 or Net 60 terms
- send the invoice email with the correct due date already set
- sync the corrected invoice due date to accounting software such as QuickBooks

## Roadmap ideas

Possible future improvements:

- use a custom client field instead of Client Groups
- add an admin UI for payment terms
- add optional per-group suspension logic
- improve compatibility notes for different WHMCS versions

## License

MIT
