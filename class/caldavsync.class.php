<?php
/* Copyright (C) 2025-2026 MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/caldavsync.class.php
 * \ingroup caldavclient
 * \brief   Classe pour gérer la synchronisation entre Dolibarr et CalDAV
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavcalendar.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldaveventmapping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/lib/caldav.lib.php';

require_once __DIR__.'/caldavsync/caldavsync_outbound.trait.php';
require_once __DIR__.'/caldavsync/caldavsync_inbound.trait.php';
require_once __DIR__.'/caldavsync/caldavsync_support.trait.php';

/**
 * Classe pour gérer la synchronisation CalDAV (logique dans class/caldavsync/*.trait.php).
 */
class CalDAVSync
{
	use CalDAVSyncOutboundTrait;
	use CalDAVSyncInboundTrait;
	use CalDAVSyncSupportTrait;

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructeur
	 *
	 * @param DoliDB $db Gestionnaire de base de données
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

}
