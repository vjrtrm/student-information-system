<?php
// PHPUnit bootstrap: autoload + in-memory SQLite schema for fast, isolated tests.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Config;

Config::setPath(dirname(__DIR__) . '/config');

/** SQLite-compatible mirror of the Module 1 schema (MySQL migrations live in database/migrations). */
function sis_test_schema(): array
{
    return [
        "CREATE TABLE departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, code TEXT NOT NULL,
            level TEXT NOT NULL DEFAULT 'UG', status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT
        )",
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, email TEXT NOT NULL,
            password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'staff',
            department_id INTEGER, staff_code TEXT, mobile TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
            created_at TEXT
        )",
        "CREATE TABLE students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mobile TEXT NOT NULL, dob TEXT NOT NULL, department_id INTEGER,
            status TEXT NOT NULL DEFAULT 'active',
            failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
            created_at TEXT
        )",
        "CREATE TABLE password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL, token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT
        )",
        "CREATE TABLE login_otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            principal_type TEXT NOT NULL, principal_id INTEGER NOT NULL,
            code_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT
        )",
        "CREATE TABLE auth_audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            principal_type TEXT NOT NULL DEFAULT 'unknown', principal_id INTEGER,
            event TEXT NOT NULL, ip TEXT, user_agent TEXT, created_at TEXT
        )",
    ];
}
