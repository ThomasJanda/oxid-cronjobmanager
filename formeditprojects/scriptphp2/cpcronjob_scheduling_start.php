<?php

//include_once __DIR__."/../../../../../bootstrap.php";

if($oCronjobScheduling = \core\cpCronjobScheduling::find($_REQUEST['current_row_index']))
{
    if($datetime = $oCronjobScheduling->getNextRunDate())
    {
        echo $datetime->format('Y-m-d H:i:s');
    }
}
echo "-";