-- Copyright (C) 2025-2026 MATER Stéphane
--
-- SPDX-License-Identifier: GPL-3.0-or-later
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see https://www.gnu.org/licenses/.

-- Table pour stocker les configurations des connexions CalDAV
CREATE TABLE IF NOT EXISTS llx_caldav_connections (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) NOT NULL COMMENT 'Nom de la connexion',
    url varchar(512) NOT NULL COMMENT 'URL du serveur CalDAV',
    username varchar(255) NOT NULL COMMENT 'Nom d''utilisateur',
    password varchar(255) NOT NULL COMMENT 'Mot de passe (chiffré)',
    calendar_path varchar(512) DEFAULT NULL COMMENT 'Chemin du calendrier sur le serveur',
    color varchar(7) DEFAULT 'BECEDD' COMMENT 'Couleur d''affichage (hex)',
    enabled tinyint(1) DEFAULT 1 COMMENT 'Actif (1) ou inactif (0)',
    active_by_default tinyint(1) DEFAULT 1 COMMENT 'Actif par défaut dans l''agenda',
    ssl_verify tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=vérifier le certificat SSL, 0=désactiver (serveur de test)',
    entity integer DEFAULT 1 COMMENT 'Entité Dolibarr',
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_author integer DEFAULT NULL,
    fk_user_modif integer DEFAULT NULL
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour stocker les calendriers CalDAV découverts
CREATE TABLE IF NOT EXISTS llx_caldav_calendars (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    fk_connection integer NOT NULL COMMENT 'ID de la connexion CalDAV',
    name varchar(255) NOT NULL COMMENT 'Nom du calendrier',
    displayname varchar(255) DEFAULT NULL COMMENT 'Nom d''affichage du calendrier',
    url varchar(512) NOT NULL COMMENT 'URL du calendrier sur le serveur',
    color varchar(7) DEFAULT 'BECEDD' COMMENT 'Couleur d''affichage (hex)',
    enabled tinyint(1) DEFAULT 1 COMMENT 'Actif (1) ou inactif (0)',
    active_by_default tinyint(1) DEFAULT 1 COMMENT 'Actif par défaut dans l''agenda',
    visibility_type varchar(32) DEFAULT 'public' COMMENT 'public ou individual',
    is_default_target tinyint(1) DEFAULT 0 COMMENT 'Cible par défaut pour sync Dolibarr vers CalDAV',
    fk_user_owner integer DEFAULT NULL COMMENT 'Utilisateur Dolibarr propriétaire de ce calendrier',
    entity integer DEFAULT 1 COMMENT 'Entité Dolibarr',
    date_creation datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_author integer DEFAULT NULL,
    fk_user_modif integer DEFAULT NULL,
    INDEX idx_connection (fk_connection),
    INDEX idx_enabled (enabled),
    INDEX idx_caldav_calendars_owner (fk_user_owner, enabled),
    FOREIGN KEY (fk_connection) REFERENCES llx_caldav_connections(rowid) ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
