# JoJosPops - REST API (Backend)

Dit is de backend API voor **JoJosPops**, een platform gericht op het faciliteren van gecontroleerde pop-up en underground evenementen. De API fungeert als de centrale logische laag die data verwerkt en gevoelige gegevens beschermt tegen ongeoorloofde toegang.

De belangrijkste technische randvoorwaarde van deze API is de **Delayed Location Reveal**. Coördinaten van evenementen worden strikt in de database bewaard en pas via de endpoints vrijgegeven aan de client zodra de ingestelde begintijd (timestamp) is verstreken. Voor die tijd levert de API uitsluitend globale locatiegegevens (buurt/wijk) om vroegtijdige interceptie te voorkomen.

---

## Kenmerken & Functionaliteiten

De backend ondersteunt de volgende kernfunctionaliteiten voor de MVP:
* **Authenticatie:** Beveiligde gebruikersregistratie en login op basis van One-Time Password (OTP) verificatie.
* **Event Beheer (Pops):** Endpoints voor het aanmaken en ophalen van evenementen, inclusief specifieke parameters voor openbare, handmatige aanvraag (request) of invite-only toegang.
* **Locatiebeveiliging:** Server-side validatie die de exacte coördinaten afschermt op basis van de actuele systeemtijd versus de event-timestamp.
* **Community & Relaties:** Logica voor het verwerken van vriendschapsverzoeken en evenementuitnodigingen.
* **Veiligheids- & Verdienmodel-toggles:** Opslag en verwerking van BHV-indicaties, beveiligingsstatus en premium-gebruikersrechten.

---

## Technische Stack

* **Framework:** Laravel (REST API)
* **Taal:** PHP
* **Database:** MySQL
* **E-mail & OTP Distributie:** Mailgun via Laravel Mail component

---

## Installatie & Lokale Setup

Volg deze stappen om de API lokaal op te starten in een ontwikkelomgeving:

### 1. Systeemvereisten
Zorg ervoor dat de volgende software op de machine is geïnstalleerd:
* **PHP** (8.1 of hoger aanbevolen)
* **Composer** (Dependency manager voor PHP)
* **MySQL Server**

### 2. Repository Klonen
```bash
git clone [https://github.com/JonahBeijer/JoJosPops-API.git](https://github.com/JonahBeijer/JoJosPops-API.git)
cd JoJosPops-API
