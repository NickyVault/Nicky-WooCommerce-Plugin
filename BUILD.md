# Build Instructions for Nicky.me WooCommerce Plugin

## Overview

This plugin has separate **development** and **production** versions:

- **Development**: Contains all debug files for testing and troubleshooting
- **Production**: Clean build for WordPress.org submission (excludes debug files)

## Quick Start

### Build Production ZIP

```bash
./build-production.sh
```

This creates a clean `Nicky.zip` file ready for WordPress.org submission.

## What Gets Excluded from Production

The build script automatically removes:

### Development Files
- `includes/debug.php` - Debug functions (uses var_export)
- `includes/debug-admin-page.php` - Debug admin page
- `README-v1.1.md` - Development notes
- `docker-compose.yml` - Docker configuration

### System Files
- `.DS_Store` (macOS)
- `Thumbs.db` (Windows)
- `desktop.ini` (Windows)
- `.git/` directories
- `.gitignore` files

## File Structure

```
Nicky-WooCommerce-Plugin/
├── Nicky/                    # Main plugin directory (kept in dev)
│   ├── includes/
│   │   ├── debug.php         # ❌ Excluded from production
│   │   ├── debug-admin-page.php  # ❌ Excluded from production
│   │   └── class-*.php       # ✅ Included in production
│   ├── assets/               # ✅ Included in production
│   ├── languages/            # ✅ Included in production
│   ├── readme.txt            # ✅ Required for WordPress.org
│   ├── README.md             # ✅ Included in production
│   └── nicky-payment-gateway.php  # ✅ Main plugin file
├── build-production.sh       # Build script
├── .buildignore              # Lists files to exclude
├── BUILD.md                  # This file
└── Nicky.zip                 # Generated production ZIP

```

## Development Workflow

### 1. During Development
- Work with all files including debug helpers
- Test with `debug.php` and `debug-admin-page.php`
- Use docker-compose for local testing if needed

### 2. Before Submission to WordPress.org
```bash
# Run the build script
./build-production.sh

# This creates Nicky.zip without debug files
# Upload Nicky.zip to WordPress.org
```

### 3. Testing Production Build
```bash
# Extract and test the production ZIP locally
unzip -q Nicky.zip -d test_build/
# Check that debug files are not present
ls test_build/Nicky/includes/debug*.php
# Should show: No such file or directory
```

## Customizing the Build

### Add Files to Exclude

Edit `.buildignore` or modify `build-production.sh`:

```bash
# In build-production.sh, add:
rm -f "$BUILD_DIR/$PLUGIN_DIR/your-file-to-exclude.php"
```

### Change Output Filename

Edit `build-production.sh`:

```bash
OUTPUT_ZIP="nicky-payment-gateway.zip"  # Instead of "Nicky.zip"
```

## Version Management

The build script automatically reads the version from:
```php
// In nicky-payment-gateway.php
* Version: 1.0.1
```

Make sure to update this before building for release.

## Continuous Integration (CI/CD)

You can integrate this into GitHub Actions or similar:

```yaml
# .github/workflows/build.yml
name: Build Plugin
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Build Production ZIP
        run: ./build-production.sh
      - name: Upload Artifact
        uses: actions/upload-artifact@v2
        with:
          name: plugin-zip
          path: Nicky.zip
```

## Troubleshooting

### Build script fails with "Permission denied"
```bash
chmod +x build-production.sh
```

### ZIP file includes unwanted files
- Check `.buildignore`
- Update `build-production.sh` exclude rules
- Run `unzip -l Nicky.zip` to inspect contents

### Need to verify what's in the ZIP
```bash
unzip -l Nicky.zip | grep debug
# Should return no results if debug files are properly excluded
```

## WordPress.org Submission Checklist

Before uploading to WordPress.org, verify:

- [ ] Run `./build-production.sh` to create production ZIP
- [ ] Version number updated in `nicky-payment-gateway.php`
- [ ] Version number updated in `readme.txt`
- [ ] Changelog updated in `readme.txt`
- [ ] No debug files in ZIP: `unzip -l Nicky.zip | grep debug`
- [ ] External service disclosure present in `readme.txt`
- [ ] Tested up to version is current (WordPress 6.8+)
- [ ] File size is reasonable (< 100KB for this plugin)

## Support

For questions about the build process, contact the development team.
