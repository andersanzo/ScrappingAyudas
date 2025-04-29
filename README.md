# 🛠️ Aid URL Tracker with Change Detection - SPRI.eus

## 🇬🇧 English

### What is this project?

This project is a robust PHP-based scraping and comparison system that collects and monitors aid/grant URLs from a specific public website (spri.eus). Its main goal is to **detect changes in the URLs** of aid programs that are used in a point-based system for end users.

The client offers multiple aid programs, and every click on a program URL earns users points. However, the external website frequently changes its URLs **without warning**, which breaks the point system. This project was developed to address that problem in an automated, scalable, and intelligent way.

---

### 🧩 Features

- Automatically retrieves all aid program URLs using a hidden **JSON API** via `cURL`.
- **Paginates through multiple results** dynamically based on total pages received from the API.
- Saves results to a `.json` file with timestamped filenames.
- Every Monday, compares the new set of URLs with the previous one:
  - Detects removed URLs.
  - Detects new URLs.
  - Detects **minor changes** using `levenshtein()` for approximate matches.
- Generates a **clean HTML email report** with the differences, highlighting potential issues.
- Sends the report via email using `PHPMailer`.
- Uses a `config.php` and `config.dev.php` structure to separate credentials securely.

---

### 📦 Technologies Used

- PHP (with `cURL`, `json`, and `levenshtein`)
- Regular Expressions with `preg_match_all`
- PHPMailer
- Composer
- HTML/CSS (for email table formatting)
- DevTools + Chrome Network analysis to reverse-engineer the API

---

### 🚀 Potential Applications

- URL monitoring systems for dynamic sites.
- Track changes in content from any API-fed interface.
- Detect unauthorized edits or regressions in public-facing systems.
- Integrate with gamified systems that rely on valid link structures.

---

## 🇪🇸 Español

### ¿Qué es este proyecto?

Este proyecto es un sistema completo de scraping y comparación en PHP que recopila y monitoriza las URL de programas de ayudas desde una web pública (spri.eus). Su objetivo principal es **detectar cambios en las URLs** de las ayudas que afectan a un sistema de puntos usado por usuarios finales.

El cliente ofrece múltiples ayudas, y cada clic en una URL genera puntos. Sin embargo, el sitio externo cambia las URLs **sin avisar**, lo que rompe el sistema. Este desarrollo resuelve ese problema de forma automatizada, escalable e inteligente.

---

### 🧩 Funcionalidades

- Obtiene todas las URLs de las ayudas usando una **API JSON oculta** mediante `cURL`.
- Pagina automáticamente según el número total de páginas informado por la API.
- Guarda los resultados en archivos `.json` con nombre basado en la fecha y hora.
- Cada lunes compara las URLs nuevas con las antiguas:
  - Detecta ayudas eliminadas.
  - Detecta nuevas ayudas.
  - Detecta cambios mínimos usando `levenshtein()` para coincidencias aproximadas.
- Genera un **informe en HTML para email** con todas las diferencias.
- Envía el informe por correo usando `PHPMailer`.
- Utiliza `config.php` para credenciales y `config.dev.php` como plantilla limpia para otros usuarios.

---

### 📦 Tecnologías usadas

- PHP (`cURL`, `json`, `levenshtein`)
- Expresiones regulares con `preg_match_all`
- PHPMailer
- Composer
- HTML/CSS (formato tabla para los emails)
- DevTools + Análisis de red en Chrome para ingeniería inversa de la API

---

### 🚀 Posibles usos

- Sistemas de monitorización de URLs para webs dinámicas.
- Detectar cambios en contenidos servidos por APIs.
- Detección de ediciones no autorizadas o fallos en sistemas públicos.
- Integración con sistemas gamificados basados en estructuras de enlace válidas.

---

### 📧 Configuración

1. Clona el repositorio y configura `config.php` con tus credenciales de email.
2. Ejecuta el script manualmente o prográmalo en cron para que se ejecute los lunes.
3. Revisa tu bandeja de entrada para el reporte con tabla HTML.
4. Usa `url-json-v7.php` para ver por consola, o `url-json-v7-mail.php` para envío por correo.

---

Desarrollado con fines profesionales y educativos. No extrae datos privados ni personales.