#!/bin/bash

###############################################################################
# Nicky.me WooCommerce Plugin - Production Build Script
#
# This script creates a clean, production-ready ZIP file of the plugin
# by excluding all development and debug files, and optionally updates
# the version number across all plugin files.
#
# Usage: ./build-production.sh [version]
#        ./build-production.sh           # Interactive mode
#        ./build-production.sh 1.0.2     # Specify version
#        ./build-production.sh --no-version  # Skip version update
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_DIR="Nicky"
OUTPUT_ZIP="Nicky.zip"
BUILD_DIR="build_temp"
MAIN_FILE="$PLUGIN_DIR/nicky-payment-gateway.php"
README_FILE="$PLUGIN_DIR/readme.txt"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Nicky.me Plugin - Production Build${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if plugin directory exists
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}Error: Plugin directory '$PLUGIN_DIR' not found!${NC}"
    exit 1
fi

# Get current version from main file
CURRENT_VERSION=$(grep "^ \* Version:" "$MAIN_FILE" | head -1 | sed 's/.*Version: *//' | sed 's/ *$//')

echo -e "${CYAN}Current version: ${YELLOW}$CURRENT_VERSION${NC}"
echo ""

# Version update logic
UPDATE_VERSION=true
NEW_VERSION=""

if [ "$1" == "--no-version" ]; then
    UPDATE_VERSION=false
    echo -e "${YELLOW}Skipping version update (--no-version flag)${NC}"
    NEW_VERSION="$CURRENT_VERSION"
elif [ -n "$1" ]; then
    # Version provided as argument
    NEW_VERSION="$1"
    echo -e "${GREEN}Using version from argument: $NEW_VERSION${NC}"
else
    # Interactive mode
    echo -e "${CYAN}Version Update Options:${NC}"
    echo -e "  ${GREEN}1)${NC} Auto-increment patch version (recommended)"
    echo -e "  ${GREEN}2)${NC} Enter custom version"
    echo -e "  ${GREEN}3)${NC} Keep current version ($CURRENT_VERSION)"
    echo ""
    read -p "Choose option [1-3]: " VERSION_CHOICE
    
    case $VERSION_CHOICE in
        1)
            # Auto-increment patch version (e.g., 1.0.1 -> 1.0.2)
            IFS='.' read -ra VERSION_PARTS <<< "$CURRENT_VERSION"
            MAJOR="${VERSION_PARTS[0]}"
            MINOR="${VERSION_PARTS[1]}"
            PATCH="${VERSION_PARTS[2]}"
            PATCH=$((PATCH + 1))
            NEW_VERSION="$MAJOR.$MINOR.$PATCH"
            echo -e "${GREEN}Auto-incremented to: $NEW_VERSION${NC}"
            ;;
        2)
            read -p "Enter new version (e.g., 1.0.2): " NEW_VERSION
            if [ -z "$NEW_VERSION" ]; then
                echo -e "${RED}Error: Version cannot be empty${NC}"
                exit 1
            fi
            echo -e "${GREEN}Using custom version: $NEW_VERSION${NC}"
            ;;
        3)
            UPDATE_VERSION=false
            NEW_VERSION="$CURRENT_VERSION"
            echo -e "${YELLOW}Keeping current version: $CURRENT_VERSION${NC}"
            ;;
        *)
            echo -e "${RED}Invalid option. Exiting.${NC}"
            exit 1
            ;;
    esac
fi

echo ""

# Update version in files if needed
if [ "$UPDATE_VERSION" = true ] && [ "$NEW_VERSION" != "$CURRENT_VERSION" ]; then
    echo -e "${GREEN}Updating version from $CURRENT_VERSION to $NEW_VERSION...${NC}"
    
    # Backup files before modification
    cp "$MAIN_FILE" "$MAIN_FILE.bak"
    cp "$README_FILE" "$README_FILE.bak"
    
    # Update main plugin file - Version comment
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS (BSD sed)
        sed -i '' "s/^ \* Version: .*/ * Version: $NEW_VERSION/" "$MAIN_FILE"
        # Update NICKY_PAYMENT_GATEWAY_VERSION constant
        sed -i '' "s/define('NICKY_PAYMENT_GATEWAY_VERSION', '.*');/define('NICKY_PAYMENT_GATEWAY_VERSION', '$NEW_VERSION');/" "$MAIN_FILE"
        # Update readme.txt Stable tag
        sed -i '' "s/^Stable tag: .*/Stable tag: $NEW_VERSION/" "$README_FILE"
    else
        # Linux (GNU sed)
        sed -i "s/^ \* Version: .*/ * Version: $NEW_VERSION/" "$MAIN_FILE"
        sed -i "s/define('NICKY_PAYMENT_GATEWAY_VERSION', '.*');/define('NICKY_PAYMENT_GATEWAY_VERSION', '$NEW_VERSION');/" "$MAIN_FILE"
        sed -i "s/^Stable tag: .*/Stable tag: $NEW_VERSION/" "$README_FILE"
    fi
    
    echo -e "${GREEN}✓ Updated nicky-payment-gateway.php${NC}"
    echo -e "${GREEN}✓ Updated readme.txt${NC}"
    
    # Remove backup files
    rm -f "$MAIN_FILE.bak" "$README_FILE.bak"
    
    echo -e "${YELLOW}Note: Don't forget to update the changelog in readme.txt!${NC}"
    echo ""
fi

# Remove old zip if it exists
if [ -f "$OUTPUT_ZIP" ]; then
    echo -e "${YELLOW}Removing old $OUTPUT_ZIP...${NC}"
    rm -f "$OUTPUT_ZIP"
fi

# Remove old build directory if it exists
if [ -d "$BUILD_DIR" ]; then
    echo -e "${YELLOW}Removing old build directory...${NC}"
    rm -rf "$BUILD_DIR"
fi

# Create temporary build directory
echo -e "${GREEN}Creating temporary build directory...${NC}"
mkdir -p "$BUILD_DIR"

# Copy plugin to build directory
echo -e "${GREEN}Copying plugin files...${NC}"
cp -R "$PLUGIN_DIR" "$BUILD_DIR/"

# Remove development and debug files
echo -e "${GREEN}Removing development files...${NC}"
rm -f "$BUILD_DIR/$PLUGIN_DIR/includes/debug.php"
rm -f "$BUILD_DIR/$PLUGIN_DIR/includes/debug-admin-page.php"
rm -f "$BUILD_DIR/$PLUGIN_DIR/README-v1.1.md"
rm -f "$BUILD_DIR/$PLUGIN_DIR/docker-compose.yml"

# Remove system files
echo -e "${GREEN}Removing system files...${NC}"
find "$BUILD_DIR" -name ".DS_Store" -type f -delete
find "$BUILD_DIR" -name "Thumbs.db" -type f -delete
find "$BUILD_DIR" -name "desktop.ini" -type f -delete

# Remove git files
echo -e "${GREEN}Removing version control files...${NC}"
find "$BUILD_DIR" -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true
find "$BUILD_DIR" -name ".gitignore" -type f -delete
find "$BUILD_DIR" -name ".gitattributes" -type f -delete

# Create production ZIP
echo -e "${GREEN}Creating production ZIP file...${NC}"
cd "$BUILD_DIR"
zip -r "../$OUTPUT_ZIP" "$PLUGIN_DIR/" -q
cd ..

# Clean up build directory
echo -e "${GREEN}Cleaning up temporary files...${NC}"
rm -rf "$BUILD_DIR"

# Get file size
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    FILE_SIZE=$(ls -lh "$OUTPUT_ZIP" | awk '{print $5}')
else
    # Linux
    FILE_SIZE=$(ls -lh "$OUTPUT_ZIP" | awk '{print $5}')
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Production build completed!${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "File:    ${GREEN}$OUTPUT_ZIP${NC}"
echo -e "Size:    ${GREEN}$FILE_SIZE${NC}"
echo -e "Version: ${GREEN}$NEW_VERSION${NC}"
echo ""
echo -e "${YELLOW}This ZIP is ready for WordPress.org submission!${NC}"
echo ""

# Show what was excluded
echo -e "${BLUE}Excluded from build:${NC}"
echo -e "  - includes/debug.php"
echo -e "  - includes/debug-admin-page.php"
echo -e "  - README-v1.1.md"
echo -e "  - docker-compose.yml"
echo -e "  - .DS_Store and other system files"
echo ""

# Show next steps if version was updated
if [ "$UPDATE_VERSION" = true ] && [ "$NEW_VERSION" != "$CURRENT_VERSION" ]; then
    echo -e "${CYAN}Next Steps:${NC}"
    echo -e "  1. Update changelog in ${YELLOW}readme.txt${NC} for version $NEW_VERSION"
    echo -e "  2. Test the plugin with the new version"
    echo -e "  3. Commit changes: ${YELLOW}git add . && git commit -m \"Release v$NEW_VERSION\"${NC}"
    echo -e "  4. Tag the release: ${YELLOW}git tag v$NEW_VERSION${NC}"
    echo -e "  5. Upload ${GREEN}$OUTPUT_ZIP${NC} to WordPress.org"
    echo ""
fi
