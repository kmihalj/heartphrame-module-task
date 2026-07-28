# Vodič za Task modul

English version: [index_en.md](index_en.md)

## 1. Mentalni model

Modul odvaja **verzioniranu definiciju** zadatka od njegova **aktualnog
operativnog stanja**.

HTML dokument posjeduje:

- UUID liste i svakog zadatka;
- tekst i redoslijed zadataka;
- opseg označavanja `editors` ili `viewers`.

Task modul posjeduje:

- zadnju vrijednost izvršenosti;
- podatak tko ju je i kada promijenio;
- nepromjenjivi događaj za svaki stvarni prijelaz.

Objavljivanje ili vraćanje HTML verzije zato mijenja dostupne definicije
zadataka bez prepisivanja operativnog audit traga.

## 2. Ovisnosti i opcionalne integracije

Obavezni lanac ovisnosti je:

`Framework -> ORM -> Auth -> HTML Editor -> Task`

Task koristi samo javne servise tih paketa. Workspace se otkriva kroz javni
integracijski most HTML Editora i ostaje opcionalan. Notification je predložen
za buduće dodjele i upozorenja o rokovima, ali Task ga ne treba za učitavanje
ni spremanje stanja.

Bez Workspacea `editors` zadatke mogu mijenjati administratori samostalnog HTML
Editora. Uz Workspace naslijeđeni ACL dokumenta određuje tko smije čitati i
uređivati objavljenu stranicu.

## 3. Prijenosni model podataka

Jedina početna ORM migracija kreira:

| Tablica | Odgovornost |
| --- | --- |
| `task_item_states` | Zadnje stanje po dokumentu i UUID-u zadatka |
| `task_item_events` | Nepromjenjivi audit prijelaza |

Stanje je jedinstveno po paru `document_id + task_uuid`. Prijevodi istog
dokumenta zato dijele izvršenost, dok kopirani ili importirani dokument ne može
slučajno promijeniti izvorni čak ni kada UUID zadatka ostane jednak.

Shema koristi samo ORM i radi na SQLiteu, PostgreSQLu i MySQL/MariaDB-u.
Migracija ne sadrži seed ni probne podatke.

## 4. Sigurnost i promjena stanja

Preglednik šalje željeno stanje, ID dokumenta, jezik, UUID zadatka i CSRF token.
Poslužitelj ne vjeruje HTML atributima ni skrivenim kontrolama. Pri svakoj
promjeni:

1. zahtijeva prijavljenog korisnika;
2. ponovno učitava aktualni objavljeni dokument;
3. potvrđuje da UUID zadatka još postoji;
4. provjerava pravo čitanja dokumenta;
5. provjerava aktualni opseg prava zadatka;
6. idempotentno postavlja željeno stanje;
7. stvara audit događaj samo ako se vrijednost promijenila.

Odgovor vraća normalizirano stanje, identitet i vrijeme koji se prikazuju ispod
checkboxa. Greške se prevode standardnim HeartPhrame jezičnim datotekama modula.

## 5. Renderiranje i ponašanje verzija

Objavljeni pregled je interaktivan samo kada trenutni korisnik ima pravo
promjene. Pregled nacrta, povijest dokumenta, filesystem snapshot i ZIP export
su statični: pokazuju stanje zabilježeno tijekom renderiranja i nikada ne
izlažu aktivne kontrole za promjenu.

Renderer sva stanja stranice učitava jednim upitom. Stranica s mnogo zadataka
zato ne radi poseban upit za svaki checkbox.

HTML Editor i dalje posjeduje:

- kontrole za uređivanje i normalizaciju sourcea;
- HTML verzije i nacrte;
- privitke i export;
- samostalni i Workspace pregled dokumenta.

Task dodaje mali renderer, stylesheet, JavaScript kontroler, JSON endpointove i
servis pohrane. Editor tu integraciju otkriva opcionalno pa nastavlja raditi i
kada Task nije instaliran.

## 6. Instalacija i provjera

```bash
composer require aaieduhr/heartphrame-module-task
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
composer on-commit
```

Nakon uključivanja modula iza HTML Editora kreirajte popis zadataka kroz alatnu
traku, objavite stranicu, označite zadatak kao ovlašteni korisnik i provjerite
promjenu audit oznake. Ponovite provjeru kao čitatelj, urednik i gost kako biste
provjerili odabrani opseg.

Isključivanje Task modula ne sprječava učitavanje HTML Editora. Deklarativna
lista ostaje dio HTML sadržaja, a interaktivne kontrole stanja više se ne
renderiraju.
