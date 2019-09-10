<?php
$scriptstarttime=microtime(true);
require_once __DIR__."/../config.inc.php";

function cron_server_errorfile()
{
    $error = __DIR__ . "/error_log";
    return $error;
}

function cron_server_isrunning($pid)
{
    if($pid=="")
        return false;
        
    exec("ps $pid", $ProcessState);
    return (count($ProcessState) >= 2);
}
function cron_server_kill($pid)
{
    $command='kill -9 '.$pid;
    //echo $command;
    shell_exec($command);
}
function cron_server_start()
{
    $error = cron_server_errorfile();
    $job = realpath(__DIR__ . "/cpcronjobstarter.php");
    $sCommand = "nohup ".CRONJOB_MANAGER__PHP_INTERPRETER." -f $job > /dev/null 2>> $error & echo $!";

    echo $sCommand . "<br>";
    $PID = shell_exec($sCommand);
    return $PID;
}
    

$times=5;
$maxruntime=50;
$sleep=$maxruntime/$times;

$PID=null;
for($x=0;$x<$times;$x++)
{
    echo "SECOND: ".$x*$sleep."<br>\n";
    
    if($PID!==null)
    {
        if(cron_server_isrunning($PID))
        {
            cron_server_kill($PID);
        }
    }
    
    $PID=cron_server_start();
    
    sleep($sleep);
}

if($PID!==null)
{
    if(cron_server_isrunning($PID))
    {
        cron_server_kill($PID);
    }
}

//2019-02-26 file get to huge with unusefull information
@unlink(cron_server_errorfile());

$scriptendtime=microtime(true);
$runtime=$scriptendtime-$scriptstarttime;
echo "<br><br>";
echo "Runtime: ".floor($runtime/60)."min, ".($runtime%60)."secs\n";