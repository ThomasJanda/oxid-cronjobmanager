<?php
namespace core;

require_once __DIR__."/../config.inc.php";

/**
 * Class cpCronjobQueue
 * @package core
 *
 * @property string cpcreated
 * @property string cpid
 * @property string cpparameter
 * @property string f_cpcronjob
 * @property string cpcurrent_pid
 * @property string cpcurrent_start
 * @property int cpcurrent_tick
 * @property string cpcustomer_id
 * @property string
 */
class cpCronjobQueue extends \core\cpCronjob
{
    public function __construct(\cconfig3 $oconfig)
    {
        parent::__construct($oconfig);
        $this->_stableName = "cpcronjob_queue";
        $this->_scolumnIndex = "cpid";
    }


    protected $_oCronjob=null;
    /**
     * @return \core\cpCronjob|false
     */
    public function getCronjob()
    {
        if($this->_oCronjob===null)
        {
            $this->_oCronjob=false;
            if($o = \core\cpCronjob::find($this->f_cpcronjob))
                $this->_oCronjob = $o;
        }
        return $this->_oCronjob;
    }

    /**
     * @return int
     */
    public function getMaxRuntime()
    {
        return $this->getCronjob()->getMaxRuntime();
    }

    /**
     * @return bool
     */
    public function shouldKillProcessAfterRuntime()
    {
        return (bool) $this->shouldKillProcessAfterRuntime();
    }

    /**
     * write a log entry to this cronjob
     *
     * @param 'error'|'output'|'finish' $type
     * @param string|array $text
     */
    public function writeLog($sType,$aText)
    {
        $this->_writeLog($this->getCronjob()->getId(), $this->getId(), $sType, $aText);
    }

    /**
     * @return \core\cpCronjob[]|false
     */
    public function getCronjobChilds()
    {
        return $this->getCronjob()->getCronjobChilds();
    }

    /**
     * try to execute the cronjob
     *
     * @var string $wrapper_filepath : path to the wrapper file, that execute the cronjob
     * @var bool $force : force start, if it wasn´t started
     *
     * @return bool : when cronjob is started, return true, otherwise false
     */
    public function execute($wrapper_filepath, $force=false)
    {
        $bKillMySelf=false;
        $ret = false;
        if(!$oCronjob = $this->getCronjob() || $this->getCronjob()->cpactive=="0")
        {
            $bKillMySelf=true;
        }
        else {
            //cronjob was started
            if (!$this->isRunning()) {
                if ($this->cpcurrent_pid != "") {
                    //pid is present, means tick is finish
                    $errorFilePath = $this->_getErrorFilePath();
                    $outputFilePath = $this->_getOutputFilePath();
                    if ($this->getCronjob()->cpignore_errors == 0 && file_exists($errorFilePath) && filesize($errorFilePath) > 0) {
                        //there is an error
                        $content = file_get_contents($errorFilePath);
                        @unlink($errorFilePath);
                        @unlink($outputFilePath);
                        @unlink($this->_getFinishedFilePath());
                        @unlink($this->_getParamFilePath());

                        //write log
                        $type = "error";
                        $this->writeLog($type, $content);
                        $this->kill();

                        $bKillMySelf = true;
                    } else {
                        @unlink($errorFilePath);
                        $aParams = null;

                        //is there any output?
                        if (file_exists($outputFilePath)) {
                            //output present, write into log
                            $fileSize = filesize($outputFilePath);
                            if ($fileSize < 1024 * 1024 * 5) {
                                $content = file_get_contents($outputFilePath);
                            } else {
                                $newName = "$outputFilePath." . time();
                                $content = "output file too large! moved file to $newName";
                                rename($outputFilePath, "$outputFilePath." . time());
                                $sTo = CRONJOB_MANAGER__MAIL_ADDRESS;
                                mail($sTo, "job output too large",
                                    "Please review the following if you're responsible of this cronjob queue<br/>file size: $fileSize, Cj queue: " . $this->getId() . "/" . $this->getCronjob()->cptitle . ", moved output file to $newName");
                            }

                            $memory_peak_usage = memory_get_peak_usage(true);
                            if ($memory_peak_usage > $this->_memorySoftLimit) {
                                $msg = 'Limit of ' . $this->_memorySoftLimit . ' has been surpassed<br/> Cj queue: ' . $this->getId() . "<br/> Cj Name: " . $this->getCronjob()->cptitle;
                                $sTo = CRONJOB_MANAGER__MAIL_ADDRESS;
                                mail($sTo, 'Excessive memory usage', $msg);
                            }

                            @unlink($outputFilePath);

                            //write log
                            $type = "output";
                            $this->writeLog($type, $content);

                            //read parameter for the next tick
                            if (file_exists($this->_getParamFilePath())) {
                                $content = file_get_contents($this->_getParamFilePath());
                                @unlink($this->_getParamFilePath());

                                $aParams = @unserialize($content);
                                if (!is_array($aParams)) {
                                    $aParams = null;
                                }
                            }
                        }

                        //is finished?
                        if (file_exists($this->_getFinishedFilePath())) {
                            //yes finished
                            @unlink($this->_getFinishedFilePath());

                            //regular finish, write log
                            /*
                            $data['cpfinished_count'] = ($this->cpfinished_count + 1);
                            $data['cplast_end'] = date('Y-m-d H:i:s');
                            $data['cplast_ticks'] = $this->cpcurrent_tick;
                            $this->assign($data, true);
                            */

                            $this->kill();

                            //maybe start jobs that should asap start after this cronjob
                            $content = "FINISHED";
                            if ($aList = $this->getCronjobChilds()) {
                                foreach ($aList as $oCronjob) {
                                    $content .= "<br>\n\r";
                                    $content .= "START CRONJOB: " . $oCronjob->cptitle;
                                    $oCronjob->execute($wrapper_filepath, true);
                                }
                            }

                            //all finished, write into log
                            $type = "finish";
                            $this->writeLog($type, $content);

                            $bKillMySelf = true;

                        } else {
                            //not finished, restart with different parameters
                            $this->kill(false);
                            $this->_restart($wrapper_filepath, $aParams);
                            $ret = true;
                        }
                    }
                } else {
                    //start new

                    //cronjob has to start
                    $this->_start($wrapper_filepath);
                    $ret = true;

                }
            } else {
                //runs, but how long?
                $currentstart = strtotime($this->cpcurrent_start);
                $duration = time() - $currentstart;
                $minutes = floor($duration / 60);
                if ($minutes > $this->getMaxRuntime()) {
                    //run longer than X minutes

                    //write log
                    $type = "runtime";
                    $aContent=[];
                    $aContent[] = "Reached the *{$this->getMaxRuntime()}* minutes limit for a tick.";
                    if($this->ShouldKillProcessAfterRuntime())
                        $aContent[]="Process kill by the cronjob manager";
                    $this->writeLog($type, $aContent);

                    if($this->ShouldKillProcessAfterRuntime()) {
                        //Copy the files to a folder where we can analyze to get clues of what happened
                        $timestamp = date('YmdHis');
                        $path = "cronjob/queue_{$this->getId()}/{$timestamp}";

                        if (file_exists($this->_getErrorFilePath())) {
                            $content = file_get_contents($this->_getErrorFilePath());
                            \Storage::put("$path/error.log", $content);
                            unlink($this->_getErrorFilePath());
                        }

                        if (file_exists($this->_getOutputFilePath())) {
                            $content = file_get_contents($this->_getOutputFilePath());
                            \Storage::put("$path/output.log", $content);
                            unlink($this->_getOutputFilePath());
                        }

                        if (file_exists($this->_getFinishedFilePath())) {
                            $content = file_get_contents($this->_getFinishedFilePath());
                            \Storage::put("$path/finished.log", $content);
                            unlink($this->_getFinishedFilePath());
                        }

                        if (file_exists($this->_getParamFilePath())) {
                            $content = file_get_contents($this->_getParamFilePath());
                            \Storage::put("$path/param.log", $content);
                            unlink($this->_getParamFilePath());
                        }

                        $this->kill();
                        $bKillMySelf = true;
                    }
                }
            }
        }

        if($bKillMySelf)
        {
            //delete myself
            $sSql="delete from cpcronjob_queue where cpid='".$this->getId()."'";
            $this->getConfig()->executeNoReturn($sSql);
        }

        return $ret;
    }

    /**
     * test if the cronjob should start
     *
     * @return bool
     */
    protected function _shouldStart()
    {
        return true;
    }


    /**
     * @param $wrapper_filepath
     * @param null $aParams
     */
    public function start($wrapper_filepath, $aParams=null)
    {
        $this->_start($wrapper_filepath, $aParams);
    }

    /**
     * start the cronjob
     *
     * @param string $wrapper_filepath
     * @param string[] $aParams
     */
    protected function _start($wrapper_filepath, $aParams=null)
    {
        $pid = $this->_startCronjob($wrapper_filepath, $aParams);

        $data = [];
        $data['cpcurrent_pid'] = $pid;
        $data['cpcurrent_tick'] = 1;
        $data['cpcurrent_start'] = date("Y-m-d H:i:s");
        $this->assign($data, true);
    }

    /**
     * restart a cronjob
     *
     * @param string $wrapper_filepath
     * @param string[] $aParams
     */
    protected function _restart($wrapper_filepath,$aParams)
    {
        $pid = $this->_startCronjob($wrapper_filepath,$aParams);

        $data=[];
        $data['cpcurrent_pid']=$pid;
        $data['cpcurrent_tick']=($this->cpcurrent_tick + 1);
        $data['cpcurrent_start']=date("Y-m-d H:i:s");
        $this->assign($data,true);
    }

    /**
     * clear the log
     */
    protected function _clearLog()
    {
        $sql="delete from cpcronjob_log where f_cpcronjob='".$this->getCronjob()->getId()."' and date_add(cpcreated, interval ".$this->getCronjob()->cpkeep_logging_in_days." day) < now()";
        $this->getConfig()->executeNoReturn($sql);
    }

    /**
     * start a cronjob in the linux system
     *
     * @param string $wrapper_filepath
     * @param null|string[] $aParams
     *
     * @return string
     */
    protected function _startCronjob($wrapper_filepath, $aParams = null)
    {
        $sCommandOutput = $this->_getOutputFilePath();
        $sCommandError = $this->_getErrorFilePath();

        $paramArray = $this->getCronjobParametersArray()
            + ($aParams ?: []); // mixing both groups of parameters into a single array

        $sParam = implode(' ', array_map(function($name, $value) {
            return escapeshellarg($name) . " " . escapeshellarg($value);
        }, array_keys($paramArray), $paramArray));

        // this is a normal job
        $sCommand = "nohup ".CRONJOB_MANAGER__PHP_INTERPRETER." -f $wrapper_filepath $this->cpid $sParam > $sCommandOutput 2> $sCommandError & echo $!";

        $pid = shell_exec($sCommand);

        return $pid;
    }


    /**
     * @return false
     */
    public function getNextRunDate()
    {
        $ret = false;
        return $ret;
    }


    /**
     * @return false
     */
    public function isDue()
    {
        $ret = false;
        return $ret;
    }

    /**
     * @return false
     */
    public function getPreviousRunDate()
    {
        $ret = false;
        return $ret;
    }


    /**
     * @return false
     */
    public function getCronjobSchedulings()
    {
        $ret = false;
        return $ret;
    }


    private $cronjobParameters = null;
    /**
     * @return false|\core\inc\listing|cpCronjobParameter[]
     */
    public function getCronjobParameters()
    {
        if ($this->cronjobParameters === null) {
            /**
             * @var \core\cpCronjobParameter[]|\core\inc\listing $list
             */
            $list = \core\cpCronjobParameter::get()->where('f_cpcronjob="' . $this->getCronjob()->getId() . '" and cpactive')->orderBy('cpname')->getList();
            if ($list->count() == 0)
                $list = false;

            $this->cronjobParameters = $list;
        }

        return $this->cronjobParameters;
    }

    /**
     * Returns the parameters in an associative array.
     * @return array
     */
    public function getCronjobParametersArray(): array
    {
        $paramArray = parent::getCronjobParametersArray();

        //add query parameter
        $aQueueParameter = \json_decode($this->cpparameter,true);
        foreach($aQueueParameter as $sKey => $sValue)
        {
            if($sKey!="")
                $paramArray[$sKey] = $sValue;
        }

        return $paramArray;
    }







    /**
     * add a item to the queue
     *
     * @param string $f_cpcronjob
     * @param array $aParam
     * @param string $sDescription
     * @param string $sQueueCustomerId
     *
     * @return bool
     */
    public static function add(string $f_cpcronjob, $aParam=[], $sDescription="", $sQueueCustomerId=""):bool
    {
        $bRet = false;

        if($f_cpcronjob!="")
        {
            $bAdd = true;
            $sQueueCustomerId = trim($sQueueCustomerId);
            if($sQueueCustomerId!="")
            {
                $bAdd = false;
                $sSql="select count(*) from cpcronjob_queue 
                  where 
                  f_cpcronjob='$f_cpcronjob'
                  and cpcurrent_pid=''
                  and cpcustomer_id='".getConfig()->escapeString($sQueueCustomerId)."'";
                if(getConfig()->getScalar($sSql)=="0")
                    $bAdd = true;
            }

            if($bAdd)
            {
                $sSql="select count(*) from cpcronjob where cpid='$f_cpcronjob'";
                if(getConfig()->getScalar($sSql)!="0")
                {
                    $aData=[];
                    $aData['f_cpcronjob'] = $f_cpcronjob;
                    $aData['cpparameter'] = \json_encode($aParam);
                    $aData['cpcustomer_id'] = $sQueueCustomerId;
                    $aData['cpdescription'] = $sDescription;

                    $o = new cpCronjobQueue(getConfig());
                    $bRet = $o->assign($aData, true);
                }
            }

        }

        return $bRet;
    }

    /**
     * add a item to the queue by cronjob customer id
     * @param string $sCustomerId
     * @param array $aParam
     * @param string $sDescription
     * @param string $sQueueCustomerId
     * @return bool
     */
    public static function addByCustomerId(string $sCustomerId, $aParam=[], $sDescription="", $sQueueCustomerId=""):bool
    {
        $sSql="select cpid from cpcronjob where cpcustom_id='$sCustomerId'";
        $f_cronjob = getConfig()->getOne($sSql);

        $bRet = false;
        if($f_cronjob!="")
            $bRet = cpCronjobQueue::add($f_cronjob, $aParam,  $sDescription, $sQueueCustomerId);
        return $bRet;
    }

}