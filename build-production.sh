#!/bin/bash

###############################################################################
# Nicky.me WooCommerce Plugin - Production Build Script
#
# This script creates a clean, production-ready ZIP file of the plugin
# by excluding all development and debug files.
#
# Usage: ./build-production.sh
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_DIR="Nicky"
OUTPUT_ZIP="Nicky.zip"
BUILD_DIR="build_temp"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Nicky.me Plugin - Production Build${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if plugin directory exists
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}Error: Plugin directory '$PLUGIN_DIR' not found!${NC}"
    exit 1
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

# Get plugin version from main file
VERSION=$(grep "Version:" "$PLUGIN_DIR/nicky-payment-gateway.php" | head -1 | awk '{print $3}')

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Production build completed!${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "File: ${GREEN}$OUTPUT_ZIP${NC}"
echo -e "Size: ${GREEN}$FILE_SIZE${NC}"
echo -e "Version: ${GREEN}$VERSION${NC}"
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
