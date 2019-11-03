<?php

//require __DIR__ ."/../../../../../bootstrap.php";

$cpscheduling = $_REQUEST['cpscheduling_hidden'] ?? "";

echo "Scheduling: ".$cpscheduling."<br>";
echo "Now: ".date('Y-m-d H:i:s')."<br>";
echo "<br>";

for($x=-5;$x<=5;$x++)
{
    $ret = \core\cpCronjobScheduling::calculateDatetime($cpscheduling,$x);
    if($x==0)
    {
        echo $x.". ".($ret?"should run at this minute":"should not run at this minute")."<br>";
    }
    else
    {
        if($ret!==false)
        {
            echo $x.". ".$ret->format('Y-m-d H:i:s')."<br>";
        }
        else
        {
            echo $x.". ERROR<br>";
        }
    }
}
