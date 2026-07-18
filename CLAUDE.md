# CLAUDE.md — MOBILE_HEALTH

## Ujeedada Project
Flutter mobile health app (mHealth) — mothers, doctors, admin management system.

---

## Tech Stack

### Frontend (Mobile)
- **Framework:** Flutter (Dart)
- **Location:** `C:\Users\hp\Documents\m_Health\mobile_health\`
- **Entry:** `lib/main.dart`
- **State Management:** Provider (`lib/providers/`)
- **Packages:** http, provider, shared_preferences

### Backend
- **Language:** PHP 8.5
- **Location:** `C:\xampp\htdocs\mHealth_api\`
- **Connection:** `conn.php`
- **Server:** XAMPP (localhost)

### Database
- **Engine:** MySQL (XAMPP)
- **Driver:** PDO (mysqli — `$connectNow`)
- **Charset:** utf8mb4

---

## Qaab-dhismeedka Flutter (`mobile_health/lib/`)

```
lib/
├── config/
├── models/
├── providers/
└── screens/
    ├── admin/
    │   ├── admin_statistics_screen.dart
    │   └── admin_users_screen.dart
    ├── doctor/
    │   └── doctor_home (iyo kuwo kale)
    ├── home_screen.dart          ← Main home screen
    ├── login_screen.dart
    ├── forgot_password_screen.dart
    ├── book_appointment_screen.dart
    ├── doctors_list_screen.dart
    ├── mother_lab_tests_screen.dart
    ├── mother_danger_signs_screen.dart
    ├── mother_health_records_screen.dart
    ├── mother_hospitals_screen.dart
    └── mother_medications_screen.dart
```

---

## Qaab-dhismeedka PHP Backend (`mHealth_api/`)

```
mHealth_api/
├── conn.php                      ← DB connection (use $connectNow)
├── login.php
├── forgot_password.php
├── api.php
├── admin_register.php
├── appointments_api.php
├── dashboard_api.php
├── danger_signs_api.php
├── doctor_home_api.php
├── doctor_patients_api.php
├── doctors_api.php
├── health_records_api.php
├── hospitals_api.php
├── lab_test_api.php
├── medications_api.php
├── mother_dashboard_api.php
├── mothers_api.php
├── mothers_list_api.php
├── notifications_api.php        ← PATCH mark_read, POST mark_all_read
├── pregnancies_api.php
├── uploads/                     ← File uploads
├── PHPMailer/
├── PHPMailer-master/
└── .htaccess
```

---

## DB Connection — Sida loo Isticmaalo

```php
// Fayl kasta ku bilow:
require_once 'conn.php';
// Variable: $connectNow (mysqli object)

// Tusaale prepared statement:
$stmt = $connectNow->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
```

---

## API Endpoints (Existing)

| Method | Endpoint | Fayl |
|--------|----------|------|
| POST | /login.php | login.php |
| POST | /admin_register.php | admin_register.php |
| GET/POST | /appointments_api.php | appointments_api.php |
| GET | /dashboard_api.php | dashboard_api.php |
| GET/POST | /danger_signs_api.php | danger_signs_api.php |
| GET | /doctors_api.php | doctors_api.php |
| GET/POST | /health_records_api.php | health_records_api.php |
| GET | /hospitals_api.php | hospitals_api.php |
| GET/POST | /lab_test_api.php | lab_test_api.php |
| GET/POST | /medications_api.php | medications_api.php |
| GET | /mothers_api.php | mothers_api.php |
| GET | /mothers_list_api.php | mothers_list_api.php |
| GET/PATCH/POST | /notifications_api.php | notifications_api.php |
| GET/POST | /pregnancies_api.php | pregnancies_api.php |

---

## API Base URL

```dart
// lib/config/ ama constants file
const String baseUrl = 'http://10.0.2.2/mHealth_api'; // Android Emulator
// const String baseUrl = 'http://192.168.x.x/mHealth_api'; // Real device
```

> **Muhiim:** Android emulator `localhost` ma aqoonsato — isticmaal `10.0.2.2`

---

## Roles (User Types)

- `mother` — Hooyo (main user)
- `doctor` — Dhakhtar
- `admin` — Maamulaha

---

## Response Format (JSON)

```json
{ "success": true,  "message": "...", "data": [...] }
{ "success": false, "message": "Error description" }
```

---

## Amarada Muhiimka ah

```bash
# Flutter (VS Code terminal)
flutter run                         # Emulator: Pixel android-x64
flutter pub get                     # Packages install
flutter clean && flutter pub get    # Cache dhibaato hadduu jiro

# XAMPP
# Start Apache + MySQL ka XAMPP Control Panel
# DB URL: http://localhost/phpmyadmin
```

---

## Xeerarka Project

- Comments Soomaali + English labadaba
- `$connectNow` isticmaal — ha abuuri DB connection cusub
- Prepared statements ALWAYS — SQL injection ha ogolaan
- Response: JSON kaliya (`echo json_encode(...)`)
- Flutter: API calls dhig `services/` ama providers
- Emulator IP: `10.0.2.2` (localhost beddelkiis)

---

## Fayl-yada Muhiimka ah

| Fayl | Meesha | Ujeedada |
|------|--------|---------|
| conn.php | mHealth_api/ | DB connection |
| home_screen.dart | lib/screens/ | Main screen, _buildHeaderBg |
| login_screen.dart | lib/screens/ | Authentication |
| notifications_api.php | mHealth_api/ | Push notifications |

---

*CLAUDE.md — Mobile Health Project | XAMPP + Flutter | Updated: 2026*
