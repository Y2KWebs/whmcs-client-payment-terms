<?php

/**
 * WHMCS - Client Payment Terms by Client Group
 *
 * Version: 1.0.0
 * License: MIT
 * Author: Y2K Webs / Community Release
 *
 * What it does:
 * - Uses the client's WHMCS client group to determine invoice payment terms.
 * - Changes ONLY the invoice due date.
 * - Does NOT change the service Next Due Date or renewal logic.
 * - Runs on InvoiceCreation so the invoice email should already show the correct due date.
 *
 * Recommended WHMCS settings:
 * - Invoice Generation: your preferred value (0, 1, 7, etc.)
 * - Process Days Before Due: 0
 * - Suspend Days: set according to your operational policy
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

/**
 * Map WHMCS client group IDs to payment term days.
 *
 * Replace these IDs with the real group IDs from your installation.
 *
 * Example:
 * 4 => Net 30
 * 5 => Net 60
 *
 * Any client with no group or with an unmapped group defaults to 0 extra days
 * and therefore preserves standard WHMCS behavior.
 */
const Y2K_PAYMENT_TERMS_BY_GROUP_ID = [
    4 => 30,
    5 => 60,
];

/**
 * If true, only automatically generated invoices are affected.
 * This is usually what you want for recurring billing.
 *
 * Possible sources commonly include:
 * - autogen
 * - adminarea
 * - api
 */
const Y2K_ONLY_AUTOGEN_INVOICES = true;

/**
 * If false:
 *   due date = invoice date + term days
 *
 * If true:
 *   due date = original WHMCS invoice due date + term days
 *
 * Recommended: true
 * This preserves native WHMCS behavior for standard clients when invoices are
 * generated in advance and adds Net 30 / Net 60 on top of that.
 */
const Y2K_USE_ORIGINAL_INVOICE_DUE_DATE_AS_BASE = true;

/**
 * Optional logging to Activity Log.
 */
const Y2K_PAYMENT_TERMS_ENABLE_LOG = true;

/**
 * Main hook.
 *
 * InvoiceCreation occurs when the invoice is first created, before it is
 * finalized/delivered. This lets the invoice email go out with the corrected
 * due date already in place.
 */
add_hook('InvoiceCreation', 1, function ($vars) {

    try {
        $invoiceId = isset($vars['invoiceid']) ? (int) $vars['invoiceid'] : 0;
        $source    = isset($vars['source']) ? (string) $vars['source'] : '';

        if ($invoiceId <= 0) {
            y2kPaymentTermsLog("Aborted: invalid invoice ID.");
            return;
        }

        if (Y2K_ONLY_AUTOGEN_INVOICES && $source !== 'autogen') {
            y2kPaymentTermsLog("Skipped invoice #{$invoiceId}: source '{$source}' is not autogen.");
            return;
        }

        // Read invoice data.
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first([
                'id',
                'userid',
                'date',
                'duedate',
                'status',
            ]);

        if (!$invoice) {
            y2kPaymentTermsLog("Invoice #{$invoiceId} not found.");
            return;
        }

        $userId = (int) $invoice->userid;
        if ($userId <= 0) {
            y2kPaymentTermsLog("Invoice #{$invoiceId} has no valid user ID.");
            return;
        }

        // Safety: do not touch paid/cancelled invoices.
        $status = (string) $invoice->status;
        if (in_array($status, ['Paid', 'Cancelled', 'Refunded', 'Collections'], true)) {
            y2kPaymentTermsLog("Skipped invoice #{$invoiceId}: status '{$status}'.");
            return;
        }

        // Read client group.
        $client = Capsule::table('tblclients')
            ->where('id', $userId)
            ->first([
                'id',
                'groupid',
                'firstname',
                'lastname',
                'companyname',
            ]);

        if (!$client) {
            y2kPaymentTermsLog("Client #{$userId} not found for invoice #{$invoiceId}.");
            return;
        }

        $groupId = (int) $client->groupid;
        $termDays = array_key_exists($groupId, Y2K_PAYMENT_TERMS_BY_GROUP_ID)
            ? (int) Y2K_PAYMENT_TERMS_BY_GROUP_ID[$groupId]
            : 0;

        // Determine base date.
        $baseDate = Y2K_USE_ORIGINAL_INVOICE_DUE_DATE_AS_BASE
            ? (string) $invoice->duedate
            : (string) $invoice->date;

        if (empty($baseDate) || $baseDate === '0000-00-00') {
            y2kPaymentTermsLog("Invoice #{$invoiceId} has invalid base date '{$baseDate}'.");
            return;
        }

        $base = DateTime::createFromFormat('Y-m-d', $baseDate);
        if (!$base) {
            y2kPaymentTermsLog("Invoice #{$invoiceId} base date '{$baseDate}' could not be parsed.");
            return;
        }

        if ($termDays > 0) {
            $base->modify("+{$termDays} days");
        }

        $newDueDate = $base->format('Y-m-d');
        $currentDueDate = (string) $invoice->duedate;

        if ($newDueDate === $currentDueDate) {
            y2kPaymentTermsLog("Invoice #{$invoiceId} already has due date {$newDueDate}; no change needed.");
            return;
        }

        // Update ONLY the invoice due date.
        Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->update([
                'duedate' => $newDueDate,
            ]);

        $clientLabel = trim(
            ($client->companyname ?: ($client->firstname . ' ' . $client->lastname))
        );

        y2kPaymentTermsLog(
            "Invoice #{$invoiceId} due date updated from {$currentDueDate} to {$newDueDate} " .
            "for client #{$userId} ({$clientLabel}), group ID {$groupId}, terms {$termDays} days, source '{$source}'."
        );

    } catch (Throwable $e) {
        y2kPaymentTermsLog("Exception: " . $e->getMessage());
    }
});

/**
 * Internal logger helper.
 */
function y2kPaymentTermsLog(string $message): void
{
    if (!Y2K_PAYMENT_TERMS_ENABLE_LOG) {
        return;
    }

    logActivity("[Client Payment Terms] " . $message);
}
