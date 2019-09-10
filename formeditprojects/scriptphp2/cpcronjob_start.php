<?php

//include_once __DIR__."/../../../../inc/cconfig3.php";
//include_once __DIR__."/../../../../core/cpcronjob.php";
$oConfig = cconfig3::getInstance();

if($oCronjob = \core\cpCronjob::find($_REQUEST['current_row_index'])) {
    if ($_REQUEST['current_column_name'] == 'Next run') {
        if ($datetime = $oCronjob->getNextRunDate()) {
            echo $datetime->format('Y-m-d H:i:s');
        } else {
            echo "-";
        }
    }

    if ($_REQUEST['current_column_name'] == "Script") {
        if ($script = $oCronjob->cpscript) {
            echo str_replace('/', '/<br>', $script);
            if (!file_exists($oConfig->getBaseModulesDir() . $script)) {
                echo ' (Not exists)';
            }
        }
        else
        {
            echo "NO CRONJOB FILE PRESENT";
        }
    }
} else {
    echo "-";
}