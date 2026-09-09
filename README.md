# Festi-tikkie (ontwikkelnaam)

Festi-Tikkie is een webapplicatie voor vriendengroepen die samen naar festivals gaan waar gebruik wordt gemaakt van een cashless betaalsysteem.

Wanneer iemand een rondje drank haalt, kan het lastig zijn om bij te houden wie wat heeft besteld en hoeveel iedereen nog verschuldigd is. Festi-Tikkie houdt de bestellingen per ronde bij en helpt de groep om de kosten eerlijk te verdelen.

## Functionaliteiten

- Account aanmaken en inloggen
- Groepen aanmaken
- Vrienden toevoegen aan een groep
- Rondes aanmaken
- Groepsleden uitnodigen voor een ronde
- Producten en aantallen selecteren
- Bijhouden wanneer alle deelnemers hun bestelling hebben geplaatst
- Bijhouden van de kosten per deelnemer
- Ondersteuning voor verschillende festivals, producten en valuta

## Gebruikte technieken

### Frontend

- React
- TypeScript
- Vite
- React Router
- Axios
- React Hook Form

### Backend

- PHP
- PDO
- REST API
- PHP Sessions
- Custom SQL Query Builders

### Database

- MariaDB / MySQL

## Architectuur

De applicatie bestaat uit een React-frontend die communiceert met een PHP REST API.

De backend maakt gebruik van PDO voor de communicatie met de database. Daarnaast bevat het project eigen SQL Query Builders voor:

- `SELECT`
- `INSERT`
- `UPDATE`
- `DELETE`

Hierdoor kunnen databasequeries op een consistente en herbruikbare manier worden opgebouwd en blijft de SQL-logica gescheiden van de API-endpoints.

## Achtergrond

Festi-Tikkie is ontstaan vanuit een praktisch probleem tijdens een bezoek met vrienden aan Alcatraz Metal Festival.

Het festival maakt gebruik van een cashless betaalsysteem. Hierdoor kun je niet meer een muntje neerleggen wanneer iemand een rondje haalt. Een gezamenlijke betaalkaart is ook niet altijd eerlijk, omdat niet iedereen evenveel drinkt.

Met Festi-Tikkie kan per ronde worden bijgehouden wie er meedoet, wat iedereen bestelt en welke kosten uiteindelijk onderling verrekend moeten worden.

## Status

Festi-Tikkie is momenteel nog in ontwikkeling.
