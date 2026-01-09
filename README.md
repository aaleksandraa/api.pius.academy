# PMU Academy - Laravel Backend

## Zahtjevi

- PHP 8.2+
- Composer
- PostgreSQL 14+
- Node.js 18+ (za frontend)

## Instalacija

### 1. Kloniraj i instaliraj zavisnosti

```bash
cd backend
composer install
```

### 2. Konfiguriši environment

```bash
cp .env.example .env
php artisan key:generate
```

Uredi `.env` fajl i postavi database kredencijale:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pmu_academy
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Kreiraj bazu podataka

```sql
CREATE DATABASE pmu_academy;
```

### 4. Pokreni migracije i seedere

```bash
php artisan migrate
php artisan db:seed
```

### 5. Kreiraj storage link

```bash
php artisan storage:link
```

### 6. Pokreni server

```bash
php artisan serve
```

API će biti dostupan na `http://localhost:8000`

## Demo korisnici

| Uloga    | Email                    | Lozinka      |
|----------|--------------------------|--------------|
| Admin    | admin@pmu-academy.com    | admin123     |
| Edukator | edukator@pmu-academy.com | edukator123  |
| Student  | student@pmu-academy.com  | student123   |

## API Endpoints

### Autentifikacija
- `POST /api/login` - Prijava
- `POST /api/logout` - Odjava
- `GET /api/user` - Trenutni korisnik
- `PUT /api/password` - Promjena lozinke

### Kursevi
- `GET /api/courses` - Lista aktivnih kurseva
- `GET /api/courses/{id}` - Detalji kursa
- `GET /api/lessons/{id}` - Detalji lekcije

### Zoom snimci
- `GET /api/zoom-recordings` - Lista snimaka

### Testovi
- `GET /api/tests` - Lista aktivnih testova
- `GET /api/tests/{id}` - Detalji testa
- `POST /api/tests/{id}/submit` - Predaj test
- `GET /api/test-results` - Moji rezultati

### Feed
- `GET /api/feed` - Lista objava
- `POST /api/feed` - Nova objava
- `PUT /api/feed/{id}` - Uredi objavu
- `DELETE /api/feed/{id}` - Obriši objavu
- `POST /api/feed/{id}/pin` - Zakači/otkači objavu
- `GET /api/feed/{id}/comments` - Komentari
- `POST /api/feed/{id}/comments` - Novi komentar

### Pitanja
- `GET /api/questions` - Lista pitanja
- `POST /api/questions` - Novo pitanje
- `GET /api/questions/{id}/answers` - Odgovori
- `POST /api/questions/{id}/answers` - Novi odgovor

### Radovi
- `GET /api/works` - Lista radova
- `POST /api/works` - Novi rad
- `GET /api/works/{id}/feedback` - Feedback
- `POST /api/works/{id}/feedback` - Novi feedback

### Admin (zahtijeva admin ulogu)
- `GET /api/admin/users` - Lista korisnika
- `POST /api/admin/users` - Kreiraj korisnika
- `PUT /api/admin/users/{id}` - Uredi korisnika
- `DELETE /api/admin/users/{id}` - Obriši korisnika
- Slično za kurseve, testove, zoom snimke...
