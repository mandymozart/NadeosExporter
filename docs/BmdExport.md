# BMD Export - Berechnung von Bestellsummen

## Nettobetrag (`getLineItemsTotalNet`)

**Formel:** Summe aller Einzelpositionen

```
Nettobetrag = Σ(position.preis)
```

**Logik:**
1. Alle Positionen der Bestellung durchlaufen
2. Preise addieren
3. Nettosumme zurückgeben

**Beispiel:**
- Position A: 60,00€
- Position B: 18,75€  
- **Netto: 78,75€**

## Bruttobetrag (`getLineItemsTotalGross`)

**Formel:** Nettopreis + Steuer

```
Bruttobetrag = Σ(nettopreis × (1 + steuersatz/100))
```

**Logik:**
1. Alle Positionen durchlaufen
2. Für jede Position:
   - Nettopreis nehmen
   - Steuersatz ermitteln
   - Bruttopreis berechnen: `netto × (1 + steuer/100)`
3. Bruttosumme zurückgeben

**Beispiel:**
- Position A: 60,00€ × (1 + 20/100) = 72,00€
- Position B: 18,75€ × (1 + 20/100) = 22,50€
- **Brutto: 94,50€**

## Zweck

Diese Funktionen berechnen korrekte Bestellsummen aus den tatsächlichen Einzelpositionen, anstatt möglicherweise fehlerhafte gespeicherte Bestellbeträge zu verwenden.