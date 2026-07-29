# Deployment and rollback

Treat a plugin release and a documentation release as related but separate
outputs. Test the plugin ZIP on staging before replacing production files.

## Versioning

For a plugin behavior or asset change, update both values in
`quote-system.php`:

```php
 * Version: 1.4.2
define( 'QS_VERSION', '1.4.2' );
```

`QS_VERSION` is a fallback asset version. CSS currently uses each file's
modification time, but keeping these values synchronized avoids confusing
release identification.

Use a patch version for compatible fixes, a minor version for additive features
and a major version for deliberately incompatible data/workflow changes.

Documentation-only changes do not require a plugin version bump.

## Prepare the release

1. Fetch the latest target branch and resolve divergence before editing.
2. Review `git status` and include only intended files.
3. Run the [Testing checklist](testing.md) on staging.
4. Run the documentation strict build.
5. Review the complete diff, especially authorization, nonces, calculations and
   WooCommerce callbacks.
6. Record the commit SHA and plugin version.

There is currently no explicit database migration runner. Any schema change
must remain backward compatible or introduce a versioned migration before
release.

## Build a plugin ZIP

From the directory containing `quote-system/`:

```bash
zip -r quote-system-1.4.2.zip quote-system \
  -x "quote-system/.git/*" \
     "quote-system/site/*" \
     "quote-system/.venv/*" \
     "quote-system/__pycache__/*"
```

The `docs/`, `mkdocs.yml` and GitHub workflow are harmless in a WordPress ZIP
but are not required at runtime. They may be included for source distributions
or excluded from the production package if a smaller ZIP is preferred. Do not
exclude `dompdf/`, templates, assets or any loaded PHP file.

Inspect the archive before uploading:

```bash
unzip -l quote-system-1.4.2.zip
```

The archive must have one top-level `quote-system/` folder.

## Staging deployment

1. Back up the staging database and current plugin directory.
2. Replace/install the plugin ZIP.
3. Activate it if WordPress did not preserve activation.
4. Visit **Settings → Permalinks → Save Changes** if routes behave unexpectedly.
5. Clear WordPress, optimization, object, server and CDN caches.
6. Confirm Quote Products and taxonomy assignments still exist.
7. Run smoke, pricing, permissions, PDF, email and test-payment checks.
8. Leave representative test quotes for business acceptance.

Activation registers the post types and flushes rewrite rules. It does not
import pricing data or create all required pages automatically.

## Production deployment

Schedule production work when an office user can verify the result.

1. Confirm the exact tested commit and ZIP checksum.
2. Take a fresh database and plugin-directory backup.
3. Note quote/order activity currently in progress.
4. Deploy the tested ZIP.
5. Clear all cache layers.
6. Run a non-destructive smoke test with a dedicated test account.
7. Verify an existing quote and Quote Product, not only a new blank builder.
8. Monitor PHP, SMTP and WooCommerce logs.

Avoid changing pricing data during the code deployment unless it is an explicit
part of the release and has its own backup/export.

## Rollback

Rollback is safe only when the release did not irreversibly change stored data.

1. Stop further admin workflow/payment actions if totals or permissions are
   affected.
2. Restore the previous plugin directory/ZIP.
3. Clear all caches.
4. Re-test an existing quote and the affected workflow.
5. Restore the database only when the release altered data incorrectly and the
   business has approved losing changes made after the backup.
6. Reconcile any WooCommerce orders or emails created during the incident
   manually; reverting code does not undo external actions.

Never delete Quote posts to “clean up” a deployment failure.

## Git workflow

The current integration work is published on branch `x`. Use focused commits
and a pull request into the repository's chosen production branch. The commit
that is deployed should be identifiable from the release record.

Suggested release tagging after approval:

```bash
git tag -a v1.4.2 -m "Quote System 1.4.2"
git push origin v1.4.2
```

Only tag a commit that has actually passed staging acceptance.

## Documentation deployment

Local preview:

```bash
python -m venv .venv
. .venv/bin/activate
pip install -r requirements-docs.txt
mkdocs serve
```

Production build:

```bash
mkdocs build --strict
```

The GitHub Actions workflow builds and deploys the site when documentation
changes reach `main`, or when it is manually dispatched. In repository
**Settings → Pages**, select **GitHub Actions** as the source once.

The configured public URL is:

`https://fervillz.github.io/Quoting-System/`
