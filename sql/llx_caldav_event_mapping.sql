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

-- ============================================================================
-- Table de mapping entre événements Dolibarr et événements CalDAV
-- Permet de suivre la correspondance entre les deux systèmes
-- ============================================================================

CREATE TABLE IF NOT EXISTS llx_caldav_event_mapping
(
    rowid               INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_actioncomm       INTEGER NOT NULL COMMENT 'ID de l''événement Dolibarr (llx_actioncomm)',
    caldav_uid          VARCHAR(255) NOT NULL COMMENT 'UID unique de l''événement CalDAV',
    fk_calendar         INTEGER NOT NULL COMMENT 'ID du calendrier CalDAV (llx_caldav_calendars)',
    last_sync_date      DATETIME NOT NULL COMMENT 'Date de la dernière synchronisation',
    last_sync_direction VARCHAR(20) DEFAULT 'dolibarr' COMMENT 'Dernier maître de la synchronisation (dolibarr/caldav)',
    caldav_etag         VARCHAR(255) DEFAULT NULL COMMENT 'ETag CalDAV pour détecter les modifications',
    date_creation       DATETIME NOT NULL,
    tms                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index et contraintes
    -- Un événement Dolibarr peut être synchronisé vers plusieurs calendriers CalDAV
    UNIQUE KEY uk_caldav_event_mapping_actioncomm_calendar (fk_actioncomm, fk_calendar),
    UNIQUE KEY uk_caldav_event_mapping_caldav (caldav_uid, fk_calendar),
    KEY idx_fk_actioncomm (fk_actioncomm),
    KEY idx_caldav_uid (caldav_uid),
    KEY idx_fk_calendar (fk_calendar),
    KEY idx_last_sync_date (last_sync_date),
    
    CONSTRAINT fk_caldav_event_mapping_calendar 
        FOREIGN KEY (fk_calendar) 
        REFERENCES llx_caldav_calendars(rowid) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
