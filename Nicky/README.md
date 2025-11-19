# Nicky.me

A complete WooCommerce payment plugin for secure online payments powered by Nicky.me.

## Features

- ✅ Full WooCommerce integration
- ✅ Secure credit card processing
- ✅ Test and Live mode
- ✅ Admin dashboard with statistics
- ✅ Transaction management
- ✅ Responsive design
- ✅ Multi-language ready
- ✅ Webhook support
- ✅ Luhn algorithm validation

## Installation

1. **Copy plugin files:**
   ```bash
   cp -r nicky-woocommerce-plugin/ /path/to/wordpress/wp-content/plugins/nicky-payment-gateway/
   ```

2. **Activate plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Nicky.me"
   - Click "Activate"

3. **Configuration:**
   - Go to WooCommerce → Settings → Payments
   - Find "Nicky.me"
   - Click "Manage"
   - Configure your API keys

## Configuration

### Basic Settings

1. **Enable gateway:**
   - Check "Enable Nicky.me"

2. **Title and description:**
   - Customize title and description for your needs

3. **Test mode:**
   - Enable test mode for development/testing
   - Use test credit card: `4111 1111 1111 1111`

4. **API keys:**
   - Add your test and live API keys
   - Keep API secrets secure

### Advanced Settings

The plugin automatically creates a database table for transactions:
- `wp_nicky_payment_transactions`

## Usage

### For Customers

1. **Checkout process:**
   - Select "Nicky.me Payment" as payment method
   - Enter credit card information
   - Confirm payment

2. **Supported card formats:**
   - Visa
   - Mastercard
   - American Express
   - Discover

### For Administrators

1. **Dashboard:**
   - Go to WooCommerce → Nicky.me Payments
   - Overview of gateway status
   - Recent transactions
   - Payment statistics

2. **Transaction management:**
   - Complete transaction history
   - Detailed transaction data
   - Export functions

## Development

### File Structure

```
nicky-payment-gateway/
├── nicky-payment-gateway.php          # Main plugin file
├── includes/
│   ├── class-nicky-payment-gateway.php          # Gateway class
│   └── class-nicky-payment-gateway-admin.php    # Admin class
├── assets/
│   ├── css/
│   │   ├── checkout.css               # Frontend styles
│   │   └── admin.css                  # Admin styles
│   └── js/
│       ├── checkout.js                # Frontend JavaScript
│       └── admin.js                   # Admin JavaScript
└── README.md
```

### Hooks und Filter

Das Plugin verwendet Standard WooCommerce Hooks:

- `woocommerce_payment_gateways` - Gateway hinzufügen
- `woocommerce_update_options_payment_gateways_{id}` - Einstellungen speichern
- `woocommerce_api_{gateway_id}` - Webhook-Handler

### Anpassungen

#### Eigene Zahlungsanbieter integrieren

Bearbeite die `process_payment_request()` Methode in `class-nicky-payment-gateway.php`:

```php
private function process_payment_request($order, $card_number, $card_expiry, $card_cvc) {
    // Deine API-Integration hier
    $api_url = 'https://api.dein-zahlungsanbieter.com/charges';
    
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
        ),
        'body' => json_encode(array(
            'amount' => $order->get_total() * 100,
            'currency' => $order->get_currency(),
            'card_number' => $card_number,
            // weitere Parameter...
        )),
    );
    
    $response = wp_remote_post($api_url, $args);
    
    // Antwort verarbeiten...
}
```

## Testing

### Test-Kreditkarten

Im Test-Modus verwende folgende Karten:

- **Erfolgreiche Zahlung:** `4111 1111 1111 1111`
- **Fehlgeschlagene Zahlung:** Jede andere Nummer

### Test-Szenarien

1. **Erfolgreiche Zahlung:**
   - Verwende Test-Kreditkarte
   - Prüfe Bestellstatus
   - Prüfe Transaktions-Eintrag

2. **Fehlgeschlagene Zahlung:**
   - Verwende ungültige Karte
   - Prüfe Fehlerbehandlung
   - Prüfe Logging

## Sicherheit

### Implementierte Sicherheitsmaßnahmen

- ✅ Nonce-Verification
- ✅ Input-Sanitization
- ✅ SQL-Injection Schutz
- ✅ XSS-Schutz
- ✅ CSRF-Schutz
- ✅ Luhn-Algorithmus Validierung

### Best Practices

1. **API-Schlüssel:**
   - Niemals in Code hard-coden
   - Verwende WordPress-Optionen
   - Sichere Übertragung (HTTPS)

2. **Datenbank:**
   - Prepared Statements verwenden
   - Input validieren
   - Output escapieren

## Unterstützung

### Systemanforderungen

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- MySQL 5.6+

### Kompatibilität

Getestet mit:
- WordPress 6.3
- WooCommerce 8.0
- PHP 8.0+

## Changelog

### Version 1.0.0
- Initiale Veröffentlichung
- Grundlegende Zahlungsverarbeitung
- Admin Dashboard
- Test-Modus Support
- Responsive Design

## Lizenz

GPL v2 oder höher

## Support

Für Support und Fragen:
- E-Mail: support@example.com
- GitHub: https://github.com/username/nicky-payment-gateway

## Mitwirken

Beiträge sind willkommen! Bitte:
1. Forke das Repository
2. Erstelle einen Feature-Branch
3. Committe deine Änderungen
4. Erstelle einen Pull Request
