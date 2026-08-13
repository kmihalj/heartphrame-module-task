# Sigurnosna kopija i povrat

Task daje `task` za cjelovito stanje/povijest zadataka i `task-workspace` za zadatke vezane uz dokumente jednog područja. Izvršitelji i akteri koriste prijenosne Auth identitete, a dokumenti prijenosne Editor identitete.

Workspace scope uključuje samo retke zadataka čiji dokument pripada odabranom stablu. Zadaci povezani s dokumentom izvan odabira ne ulaze prešutno u arhivu. Redoslijed importa osiguravaju ovisnosti providera.

Operativni lockovi i privremeno stanje workera nisu backup podaci. Nakon povrata provjerite ACL zadatka kroz vraćenu stranicu i napravite jedan novi prijelaz kako biste potvrdili obavijesti i audit hookove ciljne instalacije.
