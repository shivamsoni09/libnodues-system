<?php
/**
 * ==============================================================
 *  Library No Dues Management System — Configuration
 * ==============================================================
 *  Edit the values below to match your server, or let install.sh
 *  fill them in automatically during setup.
 *
 *  This app is designed to run ALONGSIDE an existing Koha
 *  installation on the same server. It uses its own isolated
 *  database and database user — it never touches Koha's database.
 */

// ---- Database ---------------------------------------------------
// Connects to the SAME MySQL/MariaDB server Koha already uses,
// via a dedicated database (nodues_system) and dedicated user
// that install.sh creates with access to *only* that database.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'nodues_system');
define('DB_USER', 'nodues_user');
define('DB_PASS', '__DB_PASSWORD__');   // replaced automatically by install.sh

// ---- Application -----------------------------------------------
define('APP_NAME', 'Library No Dues Portal');
define('APP_URL', 'http://localhost/nodues');   // replaced automatically by install.sh
define('APP_TIMEZONE', 'Asia/Kolkata');

// ---- Koha REST API --------------------------------------------
// Koha's REST API (koha-rest-api) must be enabled on your Koha instance.
// See: /etc/koha/sites/<instance>/koha-conf.xml -> <opac_rest_api> / staff config,
// and enable the RESTBasicAuth / RESTPublicAPI syspref in Koha.
define('KOHA_API_BASE', 'http://10.153.59.94/api/v1');   // <-- change to your Koha server/instance
define('KOHA_API_USER', 'shivamsoni');                  // a dedicated Koha staff account
define('KOHA_API_PASS', 'Shivam99');

// Set to true once you've configured real Koha API credentials above.
// While false, "Check Koha" returns clearly-labelled simulated data so you
// can test the whole workflow before Koha connectivity is wired up.
define('KOHA_API_LIVE', false);

// ---- Ticket numbering -------------------------------------------
define('TICKET_PREFIX', 'ND');

// ---- Session ------------------------------------------------------
define('SESSION_NAME', 'nodues_session');

date_default_timezone_set(APP_TIMEZONE);
