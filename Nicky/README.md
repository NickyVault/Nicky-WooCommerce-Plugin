# Nicky

A complete WooCommerce payment plugin for secure online payments powered by Nicky.

## Installation

1. **Copy plugin files:**
   ```bash
   cp -r nicky-woocommerce-plugin/ /path/to/wordpress/wp-content/plugins/nicky-me/
   ```

2. **Activate plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Nicky"
   - Click "Activate"

3. **Configuration:**
   - Go to WooCommerce → Settings → Payments
   - Find "Nicky"
   - Click "Manage"
   - Configure your API keys

## Configuration

### Basic Settings

1. **Enable gateway:**
   - Check "Enable Nicky"

2. **Title and description:**
   - Customize title and description for your needs

3. **API keys:**
   - Add your test and live API keys
   - Keep API secrets secure

### Advanced Settings

The plugin automatically creates a database table for transactions:
- `wp_nicky_payment_transactions`

## Usage

### For Customers

1. **Checkout process:**
   - Select "Nicky Payment" as payment method

### For Administrators

1. **Dashboard:**
   - Go to WooCommerce → Nciky
   - Overview of gateway status
   - Recent transactions
   - Payment statistics

2. **Transaction management:**
   - Complete transaction history
   - Detailed transaction data
   - Export functions
