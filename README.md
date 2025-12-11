# KerkPoint Plugin

KerkPoint is een veelzijdige WordPress-plugin voor kerken, waarmee u diensten, collecten en evenementen eenvoudig kunt beheren en presenteren. De plugin bevat handige shortcodes, QR-code generatie en een dashboardwidget voor snelle toegang tot belangrijke informatie.

---

## 🚀 Functies

* **💰 Collectenbeheer**: Beheer collecten en toon QR-codes en betaalverzoeken aan gemeenteleden.
* **📅 Dienstenoverzicht**: Toon een overzicht van geplande diensten en voorgangers per locatie.
* **📱 QR-code generatie**: Genereer QR-codes voor collecten en evenementen.
* **📊 Dashboard-widget**: Voeg een overzichtelijke widget toe aan het WordPress-dashboard.
* **🔗 Shortcodes**: Voeg functionaliteit eenvoudig toe aan pagina's en berichten.

---

## 📌 Beschikbare Shortcodes

### `[kp_collecte]`

Toont alle actieve collectedoelen met QR-codes en betaalverzoeken.

* **Doel**: Eenvoudig doneren voor gemeenteleden via QR of link.
* **Attribuutinstellingen**: Geen. Automatisch gefilterd op geldige betaalverzoeken.

### `[kp_diensten]`

Toont een overzicht van diensten en voorgangers per locatie, gesorteerd op datum en tijd.

* **Doel**: Overzicht van diensten in een bepaalde periode vooruit.
* **Attribuutinstellingen**:

  * `dagen_vooruit` – Aantal dagen vooruit tonen.

    * Standaard: `90`
    * Voorbeeld: `[kp_diensten dagen_vooruit="30"]` toont de komende 30 dagen.

### `[kp_volgende_diensten]`

Toont alleen de eerstvolgende dag met geplande diensten, gegroepeerd per locatie.

* **Doel**: Compact overzicht van de eerstvolgende diensten, inclusief tijden en voorgangers.
* **Attribuutinstellingen**: Geen. De plugin selecteert automatisch de eerstvolgende datum met diensten.

---

## ⚙️ Installatie

1. Upload de plugin naar de `/wp-content/plugins/` directory.
2. Activeer de plugin via het menu **Plugins** in WordPress.

---

## 📝 Gebruik

* Pas de plugin aan via de instellingenpagina in het WordPress-dashboard.
* Voeg de gewenste shortcodes toe aan pagina's of berichten.

**Voorbeelden**:

```
[kp_collecte]
[kp_diensten dagen_vooruit="60"]
[kp_volgende_diensten]
```

---

## 📞 Contact

Voor vragen, ondersteuning of feedback:

* **Ontwikkelaar**: Jorian Beukens
* **E-mail**: [jorian@kerkpoint.nl](mailto:jorian@kerkpoint.nl)
* **Website**: [kerkpoint.nl](https://kerkpoint.nl)
* **GitHub**: [https://github.com/KerkPoint](https://github.com/KerkPoint)

---

## 🛡️ Licentie

Deze plugin is uitgebracht onder de **GPL-licentie**.

