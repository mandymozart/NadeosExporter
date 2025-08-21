## Provisionslisten

### Datum

In der `DateRequestTrait.getDateFromRequests()` Methode:

```php
function getDateFromRequests(Request $request): DateTime
{
    $paramsChecked = ['year', 'month'];
    $allParamsSet = 2 === count(array_intersect($paramsChecked, $request->query->keys()));
    
    if (false === $allParamsSet) {
        return (new DateTime('now'))->modify('-1 months'); // Vorheriger Monat
    }
    // ... behandelt explizite year/month Parameter falls angegeben
}
```

#### Anwendung

Ohne `year` und `month` Parameter → Vorheriger Monat (z.B. wenn heute August 2025 ist, wird standardmäßig Juli 2025 verwendet)

Mit `year` und `month` Parametern → Verwendet den angegebenen Monat

Beispiel URLs

```bash
# Verwendet vorherigen Monat (Juli 2025 wenn aktueller Monat August 2025 ist)
GET /commissions

# Verwendet spezifischen Monat 
GET /commissions?year=2024&month=12
```

### Gruppe

Über den Parameter `group` wird die Gruppe angegeben. Das Datum wird aus den `year` und `month` Parametern bestimmt. [Siehe Datum](#datum).

```bash
GET /commissions/overview?year=2024&month=12&group=NC
```


