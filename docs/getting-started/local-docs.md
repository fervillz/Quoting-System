# Run the documentation locally

The documentation uses MkDocs Material. Source pages are ordinary Markdown files under `docs/`.

## Requirements

- Python 3.10 or newer
- `pip`

## Preview changes

From the repository root:

```bash
python -m venv .venv
```

Activate it:

=== "Windows PowerShell"

    ```powershell
    .venv\Scripts\Activate.ps1
    ```

=== "macOS or Linux"

    ```bash
    source .venv/bin/activate
    ```

Install and serve:

```bash
python -m pip install -r requirements-docs.txt
mkdocs serve
```

Open `http://127.0.0.1:8000/`. MkDocs reloads the browser when a Markdown file changes.

## Validate before committing

```bash
mkdocs build --strict
```

`--strict` turns broken navigation, missing pages, and other warnings into build failures. The generated `site/` directory is disposable and should not be committed.

## Publish with GitHub Pages

The repository includes `.github/workflows/docs.yml`. After the documentation is merged into `main`:

1. Open the GitHub repository.
2. Go to **Settings → Pages**.
3. Set **Source** to **GitHub Actions**.
4. Push a documentation change to `main`, or run **Publish developer documentation** manually under **Actions**.

The configured site URL is `https://fervillz.github.io/Quoting-System/`.

## Update navigation

Adding a Markdown file does not automatically add it to the menu. Add the file to `nav` in `mkdocs.yml`, then run the strict build.
