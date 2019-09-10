<?php

/**
 * the cronjobmanager include this file and pass following array to this script
 * $_CRON_PARAMETER = array
 *
 * it contain all parameter that configured in the cronjob and also all parameter that
 * pass from the last call of the cronjob
 *
 * also the cronjobmanager pass following variable. You can switch it to true, if your job is done
 * $_CRON_FINISHED = boolean
 */
$start = (isset($_CRON_PARAMETER['start'])?$_CRON_PARAMETER['start']:0);





/**
 * do you job
 */
echo "i increase 'start' by 1<br>";
$start++;
echo "'start' has now ".$start."<br>";


/**
 * at the end, you can simply add all variables you need for the next tick into the parameter array
 * $_CRONJOB_PARAMETER = array
 *
 * Also you can finish the job by set the finish variable to true
 * $_CRONJOB_FINISH = true
 */
$_CRON_PARAMETER['start']=$start;
if($start > 5)
    $_CRON_FINISHED = true;
else
    $_CRON_FINISHED = false;
