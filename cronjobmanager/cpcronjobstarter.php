<?php
/**
 * try to start all cronjobs, that have to schedule
 *
 * this script can all in the browser to simulate a cronjob manager call
 */
namespace cronjobmanager;

require_once __DIR__ . "/../../../../bootstrap.php";

use core\cpCronjob;
use core\cpCronjobQueue;

echo "NORMAL CRONJOB<br>\n";
if($list = cpCronjob::get()->where('cpactive')->orderBy('cptitle')->getList()) {
    /**
     * @var cpCronjob $oCronJob
     */
    foreach ($list as $oCronJob) {
        $paramArray = $oCronJob->getCronjobParametersArray();

        $scriptToUse = \core\cpCronjob::getCronjobScriptPath($paramArray);

        //execute the cronjob
        $running = $oCronJob->isRunning ? 'running' : 'wait';
        echo "JOB: $oCronJob->cptitle ($running)";
        if ($oCronJob->execute($scriptToUse)) {
            echo " => STARTED/RESTARTED";
        } else {
            echo " => WAIT";
        }
        echo "<br>\n";
    }
    echo "<br>\n";
}


//execute queue
echo "QUEUE CRONJOB<br>\n";
$scriptToUse = \core\cpCronjob::getCronjobScriptPath(['cpcronjob_queue'=>1]);

echo "Test all items if finish<br>\n";
if($list = cpCronjobQueue::get()->where('cpcurrent_pid <> ""')->orderBy('cpcreated desc')->getList()) {

    /**
     * @var cpCronjobQueue $oCronJobQueue
     */
    foreach ($list as $oCronJobQueue) {

        //execute the cronjob
        $running = $oCronJobQueue->isRunning ? 'running' : 'wait';
        echo "QUEUE: {$oCronJobQueue->getCronjob()->cptitle} ($running)";
        if ($oCronJobQueue->execute($scriptToUse)) {
            echo " => STARTED/RESTARTED";
        } else {
            echo " => WAIT";
        }
        echo "<br>\n";
    }
    echo "<br>\n";
}

echo "Keep queue running<br>\n";
$iMaxExecutionQueueItems=50;
if($list = cpCronjobQueue::get()->orderBy('cpcreated asc')->limit(0,$iMaxExecutionQueueItems)->getList()) {

    /**
     * @var cpCronjobQueue $oCronJobQueue
     */
    foreach ($list as $oCronJobQueue) {

        //execute the cronjob
        if($oCronJobQueue->cpcurrent_pid=="")
        {
            $running = $oCronJobQueue->isRunning ? 'running' : 'wait';
            echo "QUEUE: {$oCronJobQueue->getCronjob()->cptitle} ($running)";
            if ($oCronJobQueue->execute($scriptToUse)) {
                echo " => STARTED/RESTARTED";
            } else {
                echo " => WAIT";
            }
            echo "<br>\n";
        }
    }
    echo "<br>\n";
}