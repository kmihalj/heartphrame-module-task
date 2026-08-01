# Integracija Task API-ja

English version: [api_en.md](api_en.md)

## Vlasništvo i scopeovi

Task posjeduje promjenjivo stanje izvršenosti i njegov nepromjenjivi audit trag.
Definicija task-liste ostaje u objavljenom dokumentu HTML Editora.

- `task:read` daje popis zadataka, čitanje jednog zadatka i njegova audita.
- `task:write` postavlja željenu vrijednost izvršenosti.

Vlasnik ključa mora smjeti čitati objavljenu stranicu. Za listu `editors`
promjena stanja dodatno zahtijeva pravo uređivanja stranice. Za listu `viewers`
stanje smije mijenjati svaki prijavljeni čitatelj.

## Javni servis

`AaiEduHr\HeartPhrameModuleTask\Api\TaskApiService` neovisan je o transportu i
ne ovisi o API modulu. Podržava:

- sve zadatke aktualno objavljene stranice;
- jedan zadatak s aktualnim stanjem;
- idempotentnu promjenu stanja;
- najnovije audit događaje s identitetom korisnika i vremenom.

Servis uvijek ponovno učitava objavljenu definiciju. Promjena stanja ne stvara
verziju stranice, a ponovljeni zahtjev s istom vrijednošću ne stvara dupli
audit događaj.

## Odnos s Editorom

Task-liste kreirajte, uklanjajte, preimenujte i preslagujte kroz izmjenu
stranice HTML Editora. Strukturirani Editor `content` ugovor prihvaća bilo koji
broj `task_list` blokova. Task API rute koriste se samo za operativno stanje
checkboxa.

Potpuna referenca HTTP ruta nalazi se u API modulu.
