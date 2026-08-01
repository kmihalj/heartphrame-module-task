# HeartPhrame Task modul

[English version](README.md)

Task modul dodaje interaktivne popise zadataka s audit tragom u verzionirane
HeartPhrame HTML dokumente.

## Ovisnosti

Obavezno, redoslijedom uključivanja:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-editor-html` (`dev-main`)
5. `aaieduhr/heartphrame-module-task` (`dev-main`)

Opcionalne integracije:

- Workspace daje naslijeđena prava pregleda i uređivanja.
- API izlaže stanje objavljenih zadataka i nepromjenjivi audit.
- Notification je pripremljen za buduće dodjele i obavijesti o rokovima.

```bash
composer require aaieduhr/heartphrame-module-task:dev-main
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
```

English documentation: [README.md](README.md)

## Mogućnosti

- kreiranje i uređivanje popisa zadataka kroz alatnu traku opcionalnog HTML Editora
- odvojeno spremanje aktualnog stanja izvršenosti od verzioniranog HTML-a
- nepromjenjivi audit događaji s korisnikom i vremenom promjene
- pravo označavanja po listi: `editors` ili `viewers`
- naslijeđeni Workspace ACL kada dokument pripada Području
- pristupačni checkboxovi s automatskim i CSRF-zaštićenim spremanjem
- statičan i siguran prikaz nacrta, povijesti, filesystem snapshota i ZIP exporta
- prijenosna ORM shema za SQLite, PostgreSQL i MySQL/MariaDB
- bez vanjskog JavaScript frameworka, seeda i probnih podataka
- opcionalni HTTP API za stanje i audit uz ACL provjeru

Definicije zadataka, tekst, redoslijed i opseg prava ostaju u dokumentu. Baza
sadrži samo promjenjivo stanje izvršenosti i njegov audit trag.

## Preduvjeti

- PHP 8.2 ili noviji s DOM ekstenzijom
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`
- `aaieduhr/heartphrame-module-editor-html`

Workspace i Notification su opcionalne integracije. Bez njih modul radi uz
samostalni HTML Editor.

## Instalacija

```bash
composer require aaieduhr/heartphrame-module-task
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
```

Modul treba uključiti nakon ORM-a, Autha i HTML Editora:

```php
'aaieduhr/heartphrame-module-orm',
'aaieduhr/heartphrame-module-auth',
'aaieduhr/heartphrame-module-editor-html',
'aaieduhr/heartphrame-module-task',
```

Jedina početna migracija kreira cijelu shemu. Namjerno ne sadrži korisnike,
popise zadataka, stanja, događaje ni druge probne podatke.

## Opsezi prava

- `editors`: stanje mogu mijenjati samo administratori samostalnog Editora ili
  korisnici s naslijeđenim Workspace pravom uređivanja.
- `viewers`: stanje može mijenjati svaki prijavljeni korisnik koji smije čitati
  objavljeni dokument.
- Gosti mogu čitati javne liste, ali ne mogu stvarati promjene s audit identitetom.

Svaka POST operacija ponovno učitava objavljeni dokument, potvrđuje da zadatak
još postoji, ponovno provjerava pravo čitanja i označavanja te zapisuje događaj
samo kada se stanje stvarno promijeni.

## Dokumentacija

Detaljna arhitektura, model pohrane, integracijski ugovor i operativne upute
nalaze se u [docs/index_hr.md](docs/index_hr.md). Vidi i
[API integraciju](docs/api_hr.md).

## Licenca

Modul je objavljen pod
[European Union Public License (EUPL) v1.2](LICENSE).

## Politika ovisnosti

Framework i interni HeartPhrame moduli zahtijevaju se s pomične grane
`dev-main`. Ovaj modul ne sprema `composer.lock`; CI dohvaća najnovija
razvojna stanja i pokreće cijeli skup provjera `composer on-commit`.
