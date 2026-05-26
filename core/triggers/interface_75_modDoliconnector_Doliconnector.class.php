<?php
/* Copyright (C) 2017-2018 	PtibogXIV        <support@ptibogxiv.net>
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
 *  \file       htdocs/core/triggers/interface_50_modTicketsup_TicketEmail.class.php
 *  \ingroup    core
 *  \brief      Fichier
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *                                           ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/doliconnector/class/dao_doliconnector.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

/**
 *  Class of triggers for doliconnector module
 */
class Interfacedoliconnector extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     *   Constructor
     *
     *   @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'doliconnector';
        $this->description = 'Triggers of the module doliconnector';
        $this->version = 'dolibarr';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * @return string Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load('admin');

        if ($this->version === 'development') {
            return $langs->trans('Development');
        } elseif ($this->version === 'experimental') {
            return $langs->trans('Experimental');
        } elseif ($this->version === 'dolibarr') {
            return DOL_VERSION;
        } elseif ($this->version) {
            return $this->version;
        }

        return $langs->trans('Unknown');
    }

    /**
     * Function called when a Dolibarr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string        $action     Event action code
     * @param CommonObject  $object     Object
     * @param User          $user       Object user
     * @param Translate     $langs      Object langs
     * @param Conf          $conf       Object conf
     * @return int                      <0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($object) || empty($object->id)) {
            return 0;
        }

        $db = $this->db;
        $result = 0;

        switch ($action) {
            case 'COMPANY_MODIFY':
                $result = $this->syncCompanyToWordpress($object, $db);
                break;
            case 'MEMBER_MODIFY':
                $result = $this->syncMemberToWordpress($object, $db);
                break;
            default:
                break;
        }

        return $result > 0 ? 1 : 0;
    }

    protected function syncCompanyToWordpress($object, $db)
    {
        dol_syslog("Trigger '" . $this->name . "' for action 'COMPANY_MODIFY' launched by " . __FILE__ . ". id=" . $object->id);

        $societeaccount = new SocieteAccount($db);
        $wordpressId = $societeaccount->getCustomerAccount($object->id, 'wordpress', '1');

        if (empty($wordpressId) || $wordpressId <= 0) {
            return 0;
        }

        $data = array(
            'name'   => trim($object->name),
            'email'  => trim($object->email),
            'url'    => trim($object->url),
            'locale' => !empty($object->default_lang) ? trim($object->default_lang) : null,
        );

        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        return $this->syncWordpressUser($wordpressId, $data, $db);
    }

    protected function syncMemberToWordpress($object, $db)
    {
        dol_syslog("Trigger '" . $this->name . "' for action 'MEMBER_MODIFY' launched by " . __FILE__ . ". id=" . $object->fk_soc);

        if (empty($object->fk_soc) || $object->fk_soc <= 0) {
            return 0;
        }

        $societeaccount = new SocieteAccount($db);
        $wordpressId = $societeaccount->getCustomerAccount($object->fk_soc, 'wordpress', '1');

        if (empty($wordpressId) || $wordpressId <= 0) {
            return 0;
        }

        $data = array(
            'first_name' => trim($object->firstname),
            'last_name'  => trim($object->lastname),
            'email'      => trim($object->email),
            'url'        => trim($object->url),
        );

        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        return $this->syncWordpressUser($wordpressId, $data, $db);
    }

    protected function syncWordpressUser($wordpressId, array $data, $db)
    {
        if (empty($data)) {
            return 0;
        }

        $wordpress = new Daodoliconnector($db);
        $result = $wordpress->doliconnectSync('PUT', '/users/' . $wordpressId, $data);

        if (is_object($result) && !empty($result->ok)) {
            return 1;
        }

        return 0;
    }
}