# Eliceu
Ǝliceu transformă alegerea liceului dintr-un proces complicat și stresant într-o experiență simplă, intuitivă și adaptată fiecărui elev de clasa a VIII-a.

Concepută ca un adevărat „magazin de licee”, platforma reunește într-un singur loc toate liceele din București și le prezintă într-un format modern, ușor de explorat. Elevii pot răsfoi carduri, aplica filtre după profil, sector, specializare sau medie, pot compara opțiuni, salva liceele preferate și accesa pagini detaliate pentru a-și construi treptat propria listă de admitere.

Elementul care diferențiază cu adevărat Ǝliceu este tehnologia din spate. Platforma integrează un predictor de admitere bazat pe Machine Learning, care analizează media și poziția elevului, alături de datele istorice ale specializărilor, pentru a estima șansele de admitere. În plus, utilizatorii au la dispoziție un test de orientare pentru identificarea profilului potrivit, o secțiune dedicată evenimentelor și noutăților, precum și alte instrumente interactive menite să îi ajute să ia decizii mai informate.

Construită pe o arhitectură modernă și protejată prin măsuri solide de securitate, precum parole criptate, autentificare în doi pași și protecție împotriva atacurilor web uzuale, Ǝliceu este mai mult decât o bază de date cu licee. Este un ghid digital inteligent, creat pentru a-i oferi fiecărui elev claritate, încredere și control asupra uneia dintre cele mai importante alegeri pentru viitorul său.

## Scop
Proiectul are scopul de a face procesul alegerii liceului mai simplu și mai accesibil pentru elevi.

## Rulare
Setați variabilele corect în fișireul `.env`:
```
DB_DRIVER=
DB_HOSTNAME=
DB_USERNAME=
DB_PASSWORD=
DB_DATABASE=
DB_PREFIX=
DB_ROOT_PASSWORD=
```

Prin docker:
```bash
docker compose up
```

Iar acum, platforma poate fi acesată pe http://localhost:8080


## Funcționalități
- Căutare și filtrare licee
- Informații despre specializări
- Test de orientare
- Chatbot AI pentru recomandări
- Predicții de admitere cu Random Forest
- Sistem de autentificare
- Design responsive

## Tehnologii
Frontend: HTML, CSS, JavaScript;
Backend: PHP, MySQL;
Inteligență Artificială: Python, FastAPI, Scikit-learn, Random Forest;
Hosting: Render;

## Structură
```
root
`--ai = implementare model ai alături de API-ul pentru a-l accesa
`--docker = dump-ul inițial pentru baza de date MySql
`--plugin = configurații de bază și funcții reutilizabile
`--src = sursă website (interactivitate prin javascript alături de stilizare prin css și html)
`--template = componente reutilizabile
```

### Modelul AI analizează:
- media elevului
- profilul dorit
- specializarea
- sectorul
- limba
- media liceului
și estimează probabilitatea de admitere.

## Cerințe de accesare
- Orice browser modern
- Orice sistem de operare
- RAM: 4GB
- Conexiune la internet

## Autori
- Farhat Fatima-Maria
- Cătrună Daria-Andreea
