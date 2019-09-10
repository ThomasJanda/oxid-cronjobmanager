<?php
namespace core;

/**
 * Class cpCronjobLog
 *
 * @property string cpcreated
 * @property string cpid
 * @property string cplog
 * @property int    cpsort_order
 * @property int    cptick
 * @property string cptype
 * @property string f_cpcronjob
 *
 * @package core
 */
class cpCronjobLog extends \core\inc\base
{
    public function __construct(\cconfig3 $oconfig)
    {
        parent::__construct("cpcronjob_log", "cpid", $oconfig, null);
    }

    /**
     * Validates if the current cronjob
     *
     * @return bool
     */
    public function finished()
    {
        return ($this->cptype === 'finish');
    }
}