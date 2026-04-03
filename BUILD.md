# Build Instructions for Nicky.me WooCommerce Plugin

## Overview

This plugin has separate **development** and **production** versions:

- **Development**: Contains all debug files for testing and troubleshooting
- **Production**: Clean build for WordPress.org submission (excludes debug files)

## Quick Start

### Build Production ZIP

The build script offers multiple ways to manage versions:

#### Interactive Mode (Recommended)
```bash
./build-production.sh
```

This will:
1. Show the current version
2. Offer to auto-increment (1.0.1 → 1.0.2)
3. Let you enter a custom version
4. Or keep the current version
5. Update all files automatically
6. Create production ZIP

#### Auto-increment Version
```bash
./build-production.sh 1.0.2
```
Specify the new version directly as an argument.

#### Build Without Version Update
```bash
./build-production.sh --no-version
```
Build the ZIP without changing any version numbers.

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
│   └── nicky-me.php               # ✅ Main plugin file
├── build-production.sh       # Build script
├── .buildignore              # Lists files to exclude
├── BUILD.md                  # This file
└── nicky-me.zip              # Generated production ZIP (directory-expected name)

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

# This creates nicky-me.zip without debug files
# Upload nicky-me.zip to WordPress.org
```

### 3. Testing Production Build
```bash
# Extract and test the production ZIP locally
unzip -q nicky-me.zip -d test_build/
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
OUTPUT_ZIP="custom-name.zip"  # Default in script is nicky-me.zip
```

## Version Management

### How Versions Are Updated

The build script automatically updates version numbers in:

1. **nicky-me.php**
   - Plugin header: `* Version: X.X.X`
   - Constant: `define('NICKY_PAYMENT_GATEWAY_VERSION', 'X.X.X');`

2. **readme.txt**
   - `Stable tag: X.X.X`

### Version Update Options

When you run `./build-production.sh`, you'll see:

```
Current version: 1.0.1

Version Update Options:
  1) Auto-increment patch version (recommended)
  2) Enter custom version
  3) Keep current version (1.0.1)

Choose option [1-3]:
```

**Option 1** - Auto-increment (recommended for bug fixes):
- 1.0.1 → 1.0.2
- 1.0.9 → 1.0.10
- Follows semantic versioning patch level

**Option 2** - Custom version (for major/minor releases):
- Enter any version: 1.1.0, 2.0.0, etc.
- Use for feature releases or breaking changes

**Option 3** - No change:
- Keeps current version
- Useful for rebuilding without version bump

### Command Line Version Control

```bash
# Auto-increment patch version interactively
./build-production.sh

# Specify exact version
./build-production.sh 1.0.2
./build-production.sh 2.0.0

# Build without changing version
./build-production.sh --no-version
```

### Don't Forget the Changelog!

The script updates version numbers automatically, but you should manually update the changelog in `readme.txt`:

```
== Changelog ==

= 1.0.2 =
* Updated: WordPress 6.8 compatibility
```

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
          path: nicky-me.zip
```

## Troubleshooting

### Build script fails with "Permission denied"
```bash
chmod +x build-production.sh
```

### ZIP file includes unwanted files
- Check `.buildignore`
- Update `build-production.sh` exclude rules
- Run `unzip -l nicky-me.zip` to inspect contents

### Need to verify what's in the ZIP
```bash
unzip -l nicky-me.zip | grep debug
# Should return no results if debug files are properly excluded
```

## WordPress.org Submission Checklist

Before uploading to WordPress.org, verify:

- [ ] Run `./build-production.sh` to create production ZIP
- [ ] Version number updated in `nicky-me.php`
- [ ] Version number updated in `readme.txt`
- [ ] Changelog updated in `readme.txt`
- [ ] No debug files in ZIP: `unzip -l nicky-me.zip | grep debug`
- [ ] External service disclosure present in `readme.txt`
- [ ] Tested up to version is current (WordPress 6.8+)
- [ ] File size is reasonable (< 100KB for this plugin)

## Support

For questions about the build process, contact the development team.
