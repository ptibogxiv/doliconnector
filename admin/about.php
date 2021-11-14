<?php
/* Copyright (C) 2017-2019 	PtibogXIV        <support@ptibogxiv.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		/doliconnector/admin/about.php
 * 	\ingroup	doliconnector
 * 	\brief		About Page
 */

// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once("../lib/doliconnector.lib.php");
require_once("../lib/PHP_Markdown/markdown.php");


// Translations
$langs->load("admin");
$langs->load("doliconnector@doliconnector");

// Access control
if (!$user->admin)
	accessforbidden();

/*
 * View
 */

llxHeader('', $langs->trans("DoliconnectorSetup"));

// Subheader
$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("DoliconnectorSetup"), $linkback, 'doliconnector@doliconnector');

// Configuration header
$head = doliconnector_admin_prepare_head();
dol_fiche_head($head, 'about', $langs->trans("Module431310Name"));

// About page goes here

print '<br>';

$buffer = file_get_contents(dol_buildpath('/doliconnector/README.md',0));
print Markdown($buffer);

dol_fiche_end();

llxFooter();

$db->close();
