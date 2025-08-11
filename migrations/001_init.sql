-- Utilisateur
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Client
CREATE TABLE clients (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    description TEXT,
    type VARCHAR(64),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Plages d'IP/Assets du client
CREATE TABLE client_assets (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE,
    asset_type VARCHAR(16) NOT NULL, -- ip_range / ip / fqdn / domain
    value VARCHAR(255) NOT NULL
);

-- Scan
CREATE TABLE scans (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE,
    scan_date DATE NOT NULL,
    scan_time TIME NOT NULL,
    scan_type VARCHAR(32) NOT NULL, -- hebdomadaire, mensuel, etc
    custom_cron VARCHAR(255), -- (stocke la règle pour les scans personnalisés)
    result_json JSONB, -- résultat complet du scan
    created_at TIMESTAMP DEFAULT NOW()
);

-- Sous-domaines découverts
CREATE TABLE discovered_subdomains (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE,
    subdomain VARCHAR(255) NOT NULL,
    ip VARCHAR(64),
    first_seen DATE NOT NULL,
    last_seen DATE NOT NULL
);

-- Résultats WhatWeb
CREATE TABLE whatweb_results (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE,
    scan_id INTEGER REFERENCES scans(id) ON DELETE CASCADE,
    domain_ip VARCHAR(255),
    port VARCHAR(16),
    raw_output TEXT
);