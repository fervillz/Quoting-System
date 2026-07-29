# Pages and shortcodes

The frontend is built with WordPress pages containing shortcodes. Use these slugs unless the PHP redirect URLs are updated too.

| Page title | Recommended slug | Shortcode |
|---|---|---|
| Trade Login | `/trade-login/` | `[quote_login]` |
| My Quotes | `/my-quotes/` | `[my_quotes]` |
| Quote Builder | `/quote-builder/` | `[quote_builder]` |
| Quote Review | `/quote-review/` | `[quote_review]` |
| Quote Thank You | `/quote-thank-you/` | `[quote_submitted]` |
| Admin Dashboard | `/admin-dashboard/` | `[admin_dashboard]` |

Optional developer preview pages:

| Purpose | Shortcode | Required URL parameter |
|---|---|---|
| Quotation HTML preview | `[quotation]` | `?quote_id=123` |
| Job Sheet HTML preview | `[jobsheet]` | `?quote_id=123` |

## Aliases

- `[joiner_login]` calls the same function as `[quote_login]`.
- `[my_quote_review]` calls the same function as `[quote_review]`.
- `[quote_admin_dashboard]` calls the same function as `[admin_dashboard]`.

`[quote_login redirect="/some-page/"]` may override the post-login destination. The default is `/my-quotes/`.

## Route dependencies

These paths are currently hard coded:

| Source | Destination |
|---|---|
| Builder save | `/quote-builder/` |
| Builder review | `/quote-review/` |
| Quote submission | `/quote-thank-you/` |
| Review trade navigation | `/my-quotes/` |
| Review admin navigation | `/admin-dashboard/` |
| Admin/trade edit | `/quote-builder/` |
| Email admin review link | `/quote-review/` |

If a page slug changes, search the repository for the old path:

```bash
rg "quote-review|quote-builder|quote-thank-you|my-quotes|admin-dashboard" .
```

## Theme integration

The plugin outputs only the main application content. The site theme remains responsible for:

- global header and footer;
- site navigation;
- loading the preferred brand fonts;
- page width outside the plugin wrappers;
- login/account links outside shortcode content.

Use a full-width page template where possible. Avoid page-builder widgets that apply typography or width rules directly to every nested `form`, `table`, `button`, or heading.

## Access behavior

| Screen | Access rule |
|---|---|
| Login | Logged-out users; logged-in users receive an “already logged in” link |
| Builder | Any logged-in user for a new quote; owner/admin rules for an existing quote |
| My Quotes | Logged in; trade users see their own, users with `edit_others_posts` see all |
| Quote Review | Owner or a user who can `edit_post` |
| Admin Dashboard | User must have `edit_others_posts` |
| PDF downloads/previews | Owner or a user who can `edit_post` |

See [Known issues](../development/known-issues.md) for the thank-you page access caveat.
