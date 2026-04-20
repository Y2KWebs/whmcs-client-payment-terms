Hi everyone,

I wanted to share a small hook we created for a billing scenario that seems pretty common, but that WHMCS still does not handle natively in a simple way.

## The problem

We wanted to support clients with different payment terms:

- immediate payment
- Net 30
- Net 60

The issue is that in WHMCS the service renewal logic and invoice timing are very closely tied together.

A common workaround is to generate invoices 30 days before the service due date, but that is not really Net 30. That is just billing earlier.

What we actually wanted was this:

- keep the service renewal logic untouched
- let WHMCS generate the invoice normally
- then adjust the **invoice due date only**
- make sure the invoice email already shows the correct due date
- allow syncing to QuickBooks with the right due date

## Our approach

We used:

- **Client Groups** to represent payment terms
- the **`InvoiceCreation`** hook to change the invoice `duedate` before the email is sent

Example logic:

- no group / unmapped group = standard WHMCS behavior
- Net 30 group = original due date + 30 days
- Net 60 group = original due date + 60 days

## Why `InvoiceCreation`?

Because `InvoiceCreated` is too late if you want the invoice email to already contain the correct due date.

## Important detail

We found that the best approach is **not** to calculate from the invoice date, but from the **original WHMCS due date**.

That way, if invoices are generated in advance, normal clients still behave exactly as before, and Net 30 / Net 60 simply extend the standard WHMCS due date.

## What this does NOT change

This only changes the **invoice due date**.

It does **not** change:

- service Next Due Date
- renewal logic
- suspension logic

So suspension still needs to be handled separately if you want different operational behavior for different client types.

## Why we are sharing it

I found many forum posts and discussions around Net 30 / invoice due dates / payment terms in WHMCS, so I thought this might be useful to others.

If there is interest, I can share the GitHub repo here as well.
