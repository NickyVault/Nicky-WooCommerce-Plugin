# Logo-Integration für Nicky Payment Gateway

## 📁 Logo-Dateien Übersicht

### Unterstützte Formate:
- **PNG:** `assets/images/logo.png` (bevorzugt)
- **SVG:** `assets/images/logo.svg` (Fallback)
- **Benutzerdefiniert:** Über Admin-Einstellungen

### Empfohlene Logo-Spezifikationen:

#### 🎨 Design-Richtlinien:
- **Größe:** 120x24 Pixel (optimal)
- **Max. Größe:** 150x30 Pixel
- **Format:** PNG mit transparentem Hintergrund
- **Auflösung:** 72-144 DPI
- **Farben:** Funktioniert auf hellem und dunklem Hintergrund

#### 📐 Technische Anforderungen:
- **Dateigröße:** < 50KB (für schnelle Ladezeiten)
- **Transparenz:** Empfohlen für PNG
- **Seitenverhältnis:** 5:1 bis 3:1 (Breite zu Höhe)

## 🔧 Logo-Implementierung

### Standard-Logo hinzufügen:
1. **Datei platzieren:**
   ```
   assets/images/logo.png
   ```

2. **Automatische Erkennung:**
   - Plugin erkennt automatisch `logo.png`
   - Fallback zu `logo.svg` wenn PNG nicht vorhanden
   - Kein Code-Änderung erforderlich

### Benutzerdefiniertes Logo:
1. **Admin-Einstellungen:**
   - WooCommerce → Einstellungen → Zahlungen
   - Nicky Payment Gateway → Verwalten
   - "Custom Logo URL" Feld ausfüllen

2. **URL-Format:**
   ```
   https://example.com/path/to/your-logo.png
   ```

## 📱 Responsive Verhalten

Das Logo wird automatisch angepasst:

### Desktop:
- Maximale Breite: 120px
- Maximale Höhe: 24px
- Position: Rechts neben dem Gateway-Namen

### Mobile:
- Maximale Breite: 100px
- Maximale Höhe: 20px
- Automatische Skalierung

### Checkout-Integration:
- Erscheint neben der Zahlungsmethode
- Skaliert automatisch bei verschiedenen Themes
- Respektiert Theme-Styles

## 🎯 Logo-Platzierung im Interface

### 1. Checkout-Seite:
```
○ Nicky Payment Gateway                    [LOGO]
  Pay securely with your credit card
```

### 2. Admin-Dashboard:
- Gateway-Konfiguration zeigt Logo-Vorschau
- Status-Checker überprüft Logo-Verfügbarkeit

### 3. WooCommerce-Einstellungen:
- Logo-URL Konfigurationsfeld
- Live-Vorschau (geplant)

## 🔍 Debugging & Troubleshooting

### Logo wird nicht angezeigt:

1. **Dateipfad prüfen:**
   ```bash
   ls -la assets/images/logo.png
   ```

2. **Berechtigungen prüfen:**
   ```bash
   chmod 644 assets/images/logo.png
   ```

3. **URL-Zugriff testen:**
   ```
   https://ihre-domain.com/wp-content/plugins/nicky-payment-gateway/assets/images/logo.png
   ```

### Cache-Probleme:
- Browser-Cache leeren
- CDN-Cache leeren (falls verwendet)
- WordPress-Cache leeren

### Theme-Konflikte:
- CSS-Inspektor verwenden
- Theme-spezifische Anpassungen möglich

## 📊 Logo-Performance

### Ladezeit-Optimierung:
- **Komprimierung:** TinyPNG oder ähnliche Tools
- **Format:** WebP für moderne Browser (geplant)
- **Lazy Loading:** Implementiert für Checkout

### SEO & Accessibility:
- Alt-Text automatisch gesetzt
- Strukturierte Daten Integration
- Screen-Reader freundlich

## 🔄 Logo-Updates

### Automatische Aktualisierung:
1. Neue Datei zu `assets/images/logo.png` hinzufügen
2. Cache leeren
3. Logo erscheint automatisch

### Rollback:
- Alte Datei wiederherstellen
- Oder benutzerdefinierte URL entfernen

## 🎨 Design-Empfehlungen

### Erfolgreiche Logo-Designs:
- ✅ Einfach und lesbar
- ✅ Funktioniert in verschiedenen Größen
- ✅ Konsistent mit Markenidentität
- ✅ Hoher Kontrast für Lesbarkeit

### Zu vermeiden:
- ❌ Zu detailliert für kleine Größen
- ❌ Schlechter Kontrast
- ❌ Zu große Dateien
- ❌ Urheberrechtlich geschützte Bilder

## 🚀 Erweiterte Features (Roadmap)

### Geplante Features:
- [ ] Multi-Logo Support (Light/Dark Theme)
- [ ] WebP Support
- [ ] Logo-Upload direkt im Admin
- [ ] Automatische Größenanpassung
- [ ] Logo-Galerie für verschiedene Anlässe
