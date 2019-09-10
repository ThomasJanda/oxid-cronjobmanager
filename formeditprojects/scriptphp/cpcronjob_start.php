<?php

class cpcronjob_start extends interfacephp
{

    public function setInterpreterIsFirstEdit()
    {
        //is do parameter present
        if (isset($_REQUEST['do'])) {
            $hsconfig = getHsConfig();
            $f_cpcronjob = $hsconfig->getIndex1Value();
            //can cronjob load?
            if ($oCronjob = \core\cpCronjob::find($f_cpcronjob)) {
                //do the job
                if($_REQUEST['do']=='kill')
                {
                    //echo "KILL";
                    $oCronjob->kill();
                }
                elseif($_REQUEST['do']=='start')
                {
                    $paramArray = $oCronjob->getCronjobParametersArray();

                    /*
                    $tools = App::make(CronJobTools::class);
                    $path = $tools->getCronjobScriptPath($paramArray);

                    //echo $path;
                    $oCronjob->execute($path,true);
                    */
                }
            }
        }
    }
}