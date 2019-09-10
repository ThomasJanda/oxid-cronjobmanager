<?php
namespace core;

class cpCronjobParameter extends \core\inc\base
{
    public function __construct(\cconfig3 $oconfig)
    {
        parent::__construct("cpcronjob_parameter", "cpid", $oconfig, null);
    }
}