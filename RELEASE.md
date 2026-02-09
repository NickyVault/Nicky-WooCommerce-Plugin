# Release Process for Nicky.me WooCommerce Plugin

Quick reference guide for releasing new versions.

## Release Workflow

### 1. Bug Fix Release (Patch Version: 1.0.1 → 1.0.2)

```bash
# Interactive build with auto-increment
./build-production.sh
# Choose option 1 (auto-increment)

# Or specify version directly
./build-production.sh 1.0.2
```

**Steps:**
1. Run build script
2. Update changelog in `readme.txt`
3. Test the plugin
4. Commit: `git add . && git commit -m "Release v1.0.2"`
5. Tag: `git tag v1.0.2`
6. Push: `git push origin main --tags`
7. Upload `Nicky.zip` to WordPress.org

### 2. Feature Release (Minor Version: 1.0.x → 1.1.0)

```bash
./build-production.sh 1.1.0
```

**Steps:**
1. Complete all feature development
2. Update `readme.txt` with new features
3. Run build script with new version
4. Update changelog in `readme.txt`
5. Test thoroughly
6. Commit and tag
7. Upload to WordPress.org

### 3. Major Release (Major Version: 1.x.x → 2.0.0)

```bash
./build-production.sh 2.0.0
```

**Steps:**
1. Complete breaking changes/major features
2. Update all documentation
3. Update `readme.txt` upgrade notices
4. Run build script
5. Extensive testing
6. Commit and tag
7. Upload to WordPress.org with upgrade notice

## Files Updated Automatically

When you run `./build-production.sh`, these files are updated:

✅ `Nicky/nicky-payment-gateway.php`
- Plugin header version
- NICKY_PAYMENT_GATEWAY_VERSION constant

✅ `Nicky/readme.txt`
- Stable tag

## Files You Must Update Manually

❌ `Nicky/readme.txt` - Changelog section

```
== Changelog ==

= 1.0.2 =
* Fixed: Issue description
* Improved: Enhancement description
* Updated: Compatibility information
```

## Quick Commands Reference

```bash
# Interactive build (recommended)
./build-production.sh

# Build specific version
./build-production.sh 1.0.2

# Build without version change
./build-production.sh --no-version

# Check current version
grep "Version:" Nicky/nicky-payment-gateway.php | head -1

# Verify ZIP contents
unzip -l Nicky.zip | grep -E "\.php$|readme\.txt"

# Check for debug files (should return nothing)
unzip -l Nicky.zip | grep debug
```

## Semantic Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0): Breaking changes, incompatible API changes
- **MINOR** (1.X.0): New features, backwards compatible
- **PATCH** (1.0.X): Bug fixes, backwards compatible

### Examples

- `1.0.1` → `1.0.2`: Fixed a bug
- `1.0.x` → `1.1.0`: Added new feature (WooCommerce Blocks support)
- `1.x.x` → `2.0.0`: Breaking change (removed old API)

## WordPress.org Submission Checklist

Before uploading to WordPress.org:

- [ ] Version number updated
- [ ] Changelog updated in `readme.txt`
- [ ] All tests pass
- [ ] No debug files in ZIP
- [ ] README.txt has correct "Tested up to" version
- [ ] Screenshots updated (if UI changed)
- [ ] Upgrade notice added (if needed)

### Verify Clean Build

```bash
# Should show NO results
unzip -l Nicky.zip | grep -E "debug|docker-compose|README-v1"

# Should show version X.X.X throughout
unzip -p Nicky.zip Nicky/nicky-payment-gateway.php | grep "Version:"
unzip -p Nicky.zip Nicky/readme.txt | grep "Stable tag:"
```

## Emergency Hotfix

For critical bugs requiring immediate fix:

```bash
# 1. Fix the bug in your code
# 2. Quick build with auto-increment
./build-production.sh 1.0.3

# 3. Update changelog with "Hotfix:" prefix
# 4. Quick test
# 5. Deploy immediately
git add . && git commit -m "Hotfix v1.0.3: Critical bug fix"
git tag v1.0.3
git push origin main --tags

# 6. Upload to WordPress.org
```

## Rollback Procedure

If you need to rollback a version:

```bash
# 1. Checkout previous version
git checkout v1.0.1

# 2. Build without version update
./build-production.sh --no-version

# 3. Upload to WordPress.org

# 4. Return to main branch
git checkout main

# 5. Fix issues and release properly
```

## Common Issues

### "Version already exists on WordPress.org"

You must use a new version number. Increment and rebuild:

```bash
./build-production.sh 1.0.3  # Or next available version
```

### "Forgot to update changelog"

No problem! The source files already have the right version:

1. Edit `Nicky/readme.txt` and add changelog
2. Rebuild with `--no-version`: `./build-production.sh --no-version`
3. Upload new ZIP

### "Need to rebuild without changing version"

```bash
./build-production.sh --no-version
```

## Version History

| Version | Date | Type | Notes |
|---------|------|------|-------|
| 1.0.1 | 2026-02-09 | Patch | WordPress.org compliance updates |
| 1.0.0 | 2025-11-18 | Major | Initial release |

---

**Pro Tip:** Always test the built ZIP before uploading to WordPress.org!

```bash
# Extract and test locally
unzip Nicky.zip -d test/
# Install in local WordPress environment
# Verify all features work
```
