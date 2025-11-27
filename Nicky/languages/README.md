# Translation template for Nicky Payment Gateway

This directory contains translation files for the Nicky Payment Gateway plugin.

## Available Languages

Currently supported languages:
- English (default)
- German (planned)

## Adding New Translations

To add a new translation:

1. Generate the POT file:
   ```bash
   wp i18n make-pot . languages/nicky-me.pot
   ```

2. Create language-specific PO files:
   ```bash
   msginit -l de_DE -o languages/nicky-me-de_DE.po -i languages/nicky-me.pot
   ```

3. Translate the strings in the PO file

4. Compile to MO file:
   ```bash
   msgfmt languages/nicky-me-de_DE.po -o languages/nicky-me-de_DE.mo
   ```

## Text Domain

The plugin uses the text domain: `nicky-me`

## Translation Functions Used

- `__()` - Translate and return
- `_e()` - Translate and echo
- `_n()` - Translate with plural forms
- `_x()` - Translate with context
