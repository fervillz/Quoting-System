# WooCommerce payments

Quote System creates up to two WooCommerce orders for one Quote:

1. a 30% deposit;
2. the remaining balance.

## Order creation

`qs_create_payment_order($quote_id, $payment_type)` accepts `deposit` or `balance`.

It:

1. checks WooCommerce functions and document access;
2. returns an existing valid order if the Quote already stores one;
3. calculates deposit or balance;
4. creates an order for the Quote author;
5. adds one `WC_Order_Item_Fee`;
6. copies customer name/email to billing fields;
7. writes Quote link/type to order meta;
8. calculates totals and saves;
9. writes order ID/payment URL to Quote meta;
10. locks the deposit amount for a deposit order.

No temporary WooCommerce product is created.

## Amount formulas

```text
total = subtotal + shipping - discount + additional charges
deposit = total × 30%
balance = current total - locked deposit
```

Once a deposit order exists, `_qs_locked_deposit_amount` prevents later pricing adjustments from changing the amount already requested. The balance reflects the current total less that locked amount.

## Meta linkage

### On the Quote

```text
_qs_deposit_order_id
_qs_deposit_payment_url
_qs_balance_order_id
_qs_balance_payment_url
_qs_locked_deposit_amount
```

### On the WooCommerce order

```text
_qs_quote_id
_qs_payment_type = deposit|balance
```

## Payment completion

`qs_handle_payment_complete()` listens to:

- `woocommerce_payment_complete`;
- `woocommerce_order_status_processing`;
- `woocommerce_order_status_completed`.

It ignores orders without valid Quote System meta. Deposit changes the Quote to `deposit_paid`; balance changes it to `paid_in_full`.

## Trade dashboard links

| Quote status | Primary action |
|---|---|
| `awaiting_deposit` | Pay Deposit if an order exists |
| `final_balance` | Pay Balance if an order exists |
| `deposit_paid` / `paid_in_full` | Download quotation PDF |
| Other submitted statuses | View Quote |

## Important limitations

- Cancelling, refunding, failing, or deleting a WooCommerce order does not roll the Quote status back.
- Creating the final balance does not currently send a dedicated customer email.
- “Resend Deposit” reuses the stored order; it does not generate a new order.
- Order billing only receives first-name text and email, not a parsed address.
- Tax behavior follows the fee/order configuration and has no Quote System tax rules.
- A payment URL cached on the Quote may become stale; `qs_get_quote_payment_url()` regenerates it from the order object.

Any refund/cancellation workflow should be designed explicitly rather than added to the existing payment-complete callback.
