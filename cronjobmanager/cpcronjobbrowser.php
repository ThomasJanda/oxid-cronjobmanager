<?php
/**
 * Start a cronjob in the browser like on the command line
 * example:
 */
namespace cronjobmanager;

require_once __DIR__."/../../../../bootstrap.php";
$oConfig = getConfig();

$argv=array();
$argv[0]=__FILE__;
$argv[1]=$_REQUEST['f_cpcronjob'];
$oCronjob = \core\cpCronjob::find($argv[1]);

if(!isset($_REQUEST['_CRON_PARAMETER']))
{
    $_REQUEST['_CRON_PARAMETER']=array();

    //first load, get all parameter
    //load cronjob
    if($aParameter = $oCronjob->getCronjobParameters())
    {
        foreach($aParameter as $oParameter)
        {
            $_REQUEST['_CRON_PARAMETER'][$oParameter->cpname]=$oParameter->cpvalue;
        }
    }
}

echo '<!doctype html>
<html lang="en">
<body>';

//convert all in argv
$start=1;
foreach($_REQUEST['_CRON_PARAMETER'] as $key=>$value)
{
    $argv[$start * 2]=$key;
    $argv[($start * 2) + 1]=$value;
    $start++;
}


//include cronjob file
$_CRON_PARAMETER = array();
$_CRON_FINISHED = true;

ob_start();
include_once __DIR__."/cpcronjobcommandline.php";
$content = ob_get_contents();
ob_end_clean();
//$content = str_replace("<script", "<scriptdisabled",$content);
//$content = str_replace("</script", "</scriptdisabled",$content);
echo '<pre>';
$content = htmlentities($content);
echo $content;
echo '</pre>';


//refresh if nessesary
if(!$_CRON_FINISHED)
{
    echo '<form id="cronjob_form" enctype="multipart/form-data" action="'.basename(__FILE__).'?f_cpcronjob='.$argv[1].'" method="POST">';
    echo '<input type="hidden" name="f_cpcronjob" value="'.$argv[1].'">';
    echo '<input type="hidden" name="uid" value="'.uniqid("").'">';
    if(is_array($_CRON_PARAMETER))
    {
        foreach($_CRON_PARAMETER as $key => $value)
        {
            echo '<input type="hidden" name="_CRON_PARAMETER['.$key.']" value="'.$value.'">';
            echo '_CRON_PARAMETER['.$key.'] = '.$value.'<br>';
        }
    }
    else
    {
        echo '<input type="hidden" name="_CRON_PARAMETER[empty]" value="">';
    }
    echo '</form>';
    echo '<script> document.getElementById("cronjob_form").submit(); </script>';
}
else
{
    echo "DONE";
}

echo '</body>
</html>';