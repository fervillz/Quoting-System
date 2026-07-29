# Emails

All Quote System messages pass through `email.php`.

## Transport

```php
qs_send_email( $to, $subject, $message, $headers, $attachments );
```

Default header:

```text
Content-Type: text/html; charset=UTF-8
```

`qs_send_admin_email()` sends to WordPress option `admin_email`.
`qs_send_customer_email()` reads Quote meta `_customer_email`.

`wp_mail()` returning `true` only means WordPress handed the message to its mail transport. It does not prove delivery.

## New submission

Trigger: trade user submits a draft from Quote Review.

| Item | Value |
|---|---|
| Function | `qs_email_quote_submitted()` |
| Recipient | WordPress `admin_email` |
| Subject | `New Quote Submitted - {quote number}` |
| Template | `templates/email-admin.php` |
| Main link | `/quote-review/?quote_id={id}` |

## Deposit request/approval

Trigger: Admin Request Deposit or Resend Deposit.

| Item | Value |
|---|---|
| Function | `qs_email_quote_approved()` |
| Recipient | `_customer_email` |
| Subject | `Quote Approved - {quote number}` |
| Template | `templates/email-customer.php` |
| Main link | WooCommerce deposit payment URL if available |

Mark as Approved without Request Deposit does not send this message.

## Shared fragments

Both email bodies include:

- `templates/email-header.php`;
- `templates/email-footer.php`.

Templates use inline styles because many email clients strip normal stylesheets.

## Add an email

1. Create an escaped template under `templates/`.
2. Render it with `qs_render_email_template()`.
3. Send through the appropriate wrapper.
4. Trigger it only after the saved status/order is correct.
5. Log/test the return value.
6. Test in multiple real email clients.

## Troubleshooting

1. Confirm `_customer_email` or `admin_email`.
2. Confirm the action actually calls the email function.
3. Install/configure SMTP logging.
4. Check `wp_mail_failed` logs from the mail plugin/site.
5. Open payment URL as the customer.
6. Verify no template produces PHP warnings.

## Current gaps

- no dedicated final-balance email;
- no payment receipt email owned by this plugin (WooCommerce may send its own);
- no editable plugin email settings;
- no retry/log record in Quote meta;
- admin/customer templates duplicate some wrapper markup despite shared header/footer files.
