# Codici a barre

L'helper legacy `createBarcode()` genera file PNG. Oltre a Code 128, Code 39,
Code 25 e Codabar, accetta EAN-13 ed EAN-8:

```php
createBarcode('/tmp/prodotto.png', '4006381333931', 80, 'horizontal', 'ean13');
createBarcode('/tmp/prodotto-compatto.png', '96385074', 80, 'horizontal', 'ean8');
```

Sono accettati anche i nomi `ean-13` ed `ean-8`. Il valore può contenere tutte
le cifre oppure omettere la cifra di controllo (12 cifre per EAN-13, 7 per
EAN-8): in quel caso il core la calcola. Se la cifra è presente ma errata,
l'helper solleva `InvalidArgumentException` invece di produrre un codice non
valido.

La codifica pura è disponibile in `Wonder\Support\Barcode\Ean` per validare o
preparare un GTIN senza creare un'immagine.
