# Projectprincipes voor TinyPortal

Toets elke vervolgactie in deze repository aan de onderstaande principes.

## Scheid schermafhandeling en backendlogica

- Plaats schermafhandeling in de templatebestanden onder `Themes/default/`.
- Plaats backendfuncties in de bronbestanden onder `Sources/`. Algemene functies die door meerdere bronbestanden worden gebruikt, mogen in de code onder `TinyPortal/` worden ondergebracht.
- Elk templatebestand heeft in principe een eigen bijbehorend bronbestand onder `Sources/`.
- Controleer bij elke codewijziging die een van beide raakt of de schermafhandeling en backendlogica gescheiden blijven en op de juiste plek staan.

## Organiseer code naar functie

Plaats code onder `TinyPortal/` in het bestand dat past bij de verantwoordelijkheid van de code.

- `Article.php` behandelt artikelen.
- `Shout.php` behandelt de shoutbox.
- `Block.php` behandelt blokken.
- `Admin.php` bevat uitsluitend beheerfuncties.
- `Base.php` en `Util.php` zijn voorbeelden van bestanden voor algemene code.

## Gebruik taalvariabelen voor gebruikersteksten

- Verwijs voor elke nieuwe tekst die aan de gebruiker wordt getoond in het templatebestand naar een taalvariabele.
- Definieer die taalvariabele in het taalbestand dat bij het template hoort, onder `Themes/default/languages/`.
- In `install.php` worden gebruikersteksten rechtstreeks opgenomen, zonder taalvariabelen.

## Lever correcties ter beoordeling aan

- Lever bij elk correctieverzoek de volledige gecorrigeerde bronbestanden afzonderlijk aan, met hun beoogde paden in de repository.
- Bewaar de bestanden ter beoordeling in `.review/` in de hoofdmap van de repository (`C:\GitHub\TinyPortal\.review\`). Behoud binnen die map het relatieve pad van elk bestand in de repository.
- Maak alleen rechtstreeks wijzigingen in de repository als de gebruiker daar expliciet om vraagt.

## Gebruik de vaste map voor JavaScript-bestanden

Plaats alle JavaScript-bestanden onder `Themes/default/scripts/tinyportal/`, relatief aan de hoofdmap van de repository.

## Stem bestandsversies af op de doelversie

- Werk bij een inhoudelijke wijziging aan een bestand met een versienummer ook het versienummer bij naar de doelversie van de wijziging.
- Behoud het bestaande versienummer van bestanden waarvan de inhoud gelijk blijft.
