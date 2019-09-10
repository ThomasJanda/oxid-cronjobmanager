<?php
/**
 * start only one cronjob and manage the parameter and the output
 * this file was call from commandline
 */
namespace cronjobmanager;

require_once __DIR__."/../../../../bootstrap.php";
$oConfig = getConfig();

$_CRON_CPCRONJOBQUEUE_CPID = $argv[1];
$_CRON_PARAMETER = $_REQUEST;
$_CRON_FINISHED = true;
$_CRON_START_TIMESTAMP=time();

//load cronjob
$oCronjobQueue = \core\cpCronjobQueue::find($_CRON_CPCRONJOBQUEUE_CPID);
$oCronjob = $oCronjobQueue->getCronjob();

//collect all parameters
for ($x = 2; $x < count($argv); $x = $x + 2) {
    $name = $argv[$x];
    $value = $argv[$x + 1];
    $_CRON_PARAMETER[$name] = $value;
}


//get the path to the script
$script = $oCronjob->getScriptFilePath();
if($script==false)
{
    //script not present
    //create an error
    $type="error";
    $content="Can not find script (".$oCronjob->cpscript.")";
    $oCronjobQueue->writeLog($type,$content);
    $oCronjobQueue->kill();
    die("");
}
unset($oCronjob);
unset($oCronjobQueue);
unset($oConfig);
$_CRON_PARAMETER_START = $_CRON_PARAMETER;



//error_reporting(E_ERROR);
//ini_set('display_errors',0);
//error_reporting(0);

$_CRON_HAS_ERROR=false;
$_CRON_ERROR=null;
try
{

    //start the script
    //parameter that can use are
    //$_CRON_PARAMETER with all parameters
    //$_CRON_FINISHED that have to set to true or false
    include_once $script;

}
catch(\Exception $e)
{
    $_CRON_HAS_ERROR=true;
    $_CRON_ERROR=$e;
}




//script finished
$_CRON_END_TIMESTAMP=time();
$nl = PHP_EOL;

//load cronjob
$oCronjobQueue = \core\cpCronjobQueue::find($_CRON_CPCRONJOBQUEUE_CPID);
$oCronjob = $oCronjobQueue->getCronjob();

//write for logging
echo $nl.$nl;

echo 'CRONJOB LOG:'.$nl;
echo '-----------------------------------------'.$nl;
echo 'Script: '.$oCronjob->getScriptFilePath().$nl;
echo 'Cronjob CPID: '.$oCronjob->getId().$nl;
echo 'Cronjob Queue CPID: '.$oCronjobQueue->getId().$nl;
echo 'Start time: '.date('Y-m-d H:i:s',$_CRON_START_TIMESTAMP).$nl;
echo 'End time: '.date('Y-m-d H:i:s',$_CRON_END_TIMESTAMP).$nl;
echo $nl;

echo '$_CRON_PARAMETER at start:'.$nl;
if (is_array($_CRON_PARAMETER_START)) {
    foreach ($_CRON_PARAMETER_START as $key => $value) {
        echo $key . "=" . $value . $nl;
    }
} else {
    echo "\$_CRON_PARAMETER_START is not array, it's content is:" . $nl . $nl;
    echo print_r($_CRON_PARAMETER_START, true);
    echo $nl;
}
echo $nl;

echo '$_CRON_PARAMETER at end:'.$nl;
if (is_array($_CRON_PARAMETER)) {
    foreach ($_CRON_PARAMETER as $key => $value) {
        echo $key . "=" . $value . $nl;
    }
} else {
    echo '$_CRON_PARAMETER is not array, it´s content is:' . $nl . $nl;
    echo print_r($_CRON_PARAMETER, true);
    echo $nl;
}
echo $nl;

echo '$_CRON_FINISHED: '.($_CRON_FINISHED==true?'YES':'NO').$nl;
echo $nl;

//write error
if($_CRON_HAS_ERROR) {

    $content = print_r($_CRON_ERROR,true);
    file_put_contents($oCronjob->getErrorFilePath(),$content);

    echo '$_CRON_ERROR: YES'.$nl;
    echo $nl;
}

//finish the tick
//write files
if(is_array($_CRON_PARAMETER))
{
    $content = serialize($_CRON_PARAMETER);
    file_put_contents($oCronjobQueue->getParamFilePath(),$content);
}
if($_CRON_FINISHED)
{
    $content = "END";
    file_put_contents($oCronjobQueue->getFinishedFilePath(),$content);
}