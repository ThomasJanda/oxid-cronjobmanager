<?php
namespace core;

require_once __DIR__."/../config.inc.php";

/**
 * Class cpCronjob
 *
 * @package core
 *
 * @property string $cpactive
 * @property string $cpcreated
 * @property string $cpcurrent_pid
 * @property string $cpcurrent_start
 * @property int $cpcurrent_tick
 * @property string $cpcustom_id
 * @property string $cpdescription
 * @property int $cpfinished_count
 * @property string $cpid
 * @property string $cpignore_errors
 * @property string $cpkeep_logging_in_days
 * @property string $cplast_end
 * @property string $cplast_start
 * @property string $cplast_ticks
 * @property string $cprun_on_holiday
 * @property string $cpscript
 * @property string $cptitle
 * @property string $f_cpcronjob_parent
 * @property int $cpmax_runtime_timeout
 * @property int $cpkill_process_after_max_runtime
 */
class cpCronjob extends \core\inc\base
{
    protected $_maxRuntimeInMinutes = 20;
    protected $_memorySoftLimit = 256 * 1024 * 1024;

    public function __construct(\cconfig3 $oconfig)
    {
        parent::__construct("cpcronjob", "cpid", $oconfig, null);
    }

    /**
     * @return int
     */
    public function getMaxRuntime()
    {
        return $this->cpmax_runtime_timeout;
    }

    /**
     * @return bool
     */
    public function shouldKillProcessAfterRuntime()
    {
        return (bool) $this->cpkill_process_after_max_runtime;
    }

    /**
     * return path to the script that should execute
     *
     * @return bool|string
     */
    public function getScriptFilePath()
    {
        $script = $this->getConfig()->getBaseModulesDir().$this->cpscript;
        if(!file_exists($script) || !is_file($script))
        {
            $script=false;
        }
        return $script;
    }

    /**
     * write a log entry to this cronjob
     *
     * @param 'error'|'output'|'finish' $type
     * @param string $text|array
     */
    public function writeLog($sType,$aText)
    {
        $this->_writeLog($this->getId(),"", $sType, $aText);
    }

    /**
     * @param string $f_cpcronjob
     * @param string $f_cronjob_queue
     * @param string $sType
     * @param string|array $aText
     */
    protected function _writeLog($f_cpcronjob,$f_cronjob_queue, $sType, $aText)
    {
        if($sType=="runtime")
        {
            $sSql="select count(*) 
            from cpcronjob_log 
            where f_cpcronjob='".$f_cpcronjob."' 
            and f_cpcronjob_queue='".$f_cronjob_queue."' 
            and cptype='runtime' 
            and date_add(cpcreated, INTERVAL ".$this->getMaxRuntime()." MINUTE) > now()";
            if($this->getConfig()->getScalar($sSql)<>"0")
            {
                //do nothing because it should only write a log each runtime limit.
                return;
            }
        }

        $text = "";
        if(is_array($aText))
            $text = implode("\n\r",$aText);
        else
            $text = $aText;

        $data=[];
        $data['f_cpcronjob']=$f_cpcronjob;
        $data['cptype']=$sType;
        $data['cplog']=$text;
        $data['cptick']=$this->cpcurrent_tick;
        $data['f_cpcronjob_queue']=$f_cronjob_queue;
        $o = new \core\cpCronjobLog($this->getConfig());
        $o->assign($data,true);

        if($sType=="error" || $sType=="runtime")
        {
            $this->sendMessage($sType, $text);
        }
    }

    /**
     * get path to the tmp folder
     *
     * @return string
     */
    protected function _getTmpPath()
    {
        $path = $this->getConfig()->getBaseTmpDir();
        $path = rtrim($path,"/")."/cronjobs";
        @mkdir($path);
        return $path."/";
    }

    public function getTmpPath()
    {
        return $this->_getTmpPath();
    }

    /**
     * get path to the finish file
     * @return string
     */
    public function getFinishedFilePath()
    {
        return $this->_getFinishedFilePath();
    }

    /**
     * get path to the finish file
     * @return string
     */
    protected function _getFinishedFilePath()
    {
        return $this->_getTmpPath().$this->getId().".finish.txt";
    }

    /**
     * get path to the parameter file
     * @return string
     */
    public function getParamFilePath()
    {
        return $this->_getParamFilePath();
    }

    /**
     * get path to the parameter file
     * @return string
     */
    protected function _getParamFilePath()
    {
        return $this->_getTmpPath().$this->getId().".param.txt";
    }

    /**
     * get path to the error file
     * @return string
     */
    public function getErrorFilePath()
    {
        return $this->_getErrorFilePath();
    }

    /**
     * get path to the error file
     * @return string
     */
    protected function _getErrorFilePath()
    {
        return $this->_getTmpPath().$this->getId().".error.txt";
    }

    /**
     * get path to the output file
     * @return string
     */
    protected function _getOutputFilePath()
    {
        return $this->_getTmpPath().$this->getId().".output.txt";
    }

    /**
     * is cronjob running
     * @return bool
     */
    public function isRunning()
    {
        $ret = false;
        if ($this->cpcurrent_pid != "") {
            exec("ps " . $this->cpcurrent_pid, $ProcessState);

            $ret = (count($ProcessState) >= 2);
        }
        return $ret;
    }

    /**
     * kill cronjob
     *
     * @param bool $clearTick
     */
    public function kill($clearTick=true)
    {
        if ($this->cpcurrent_pid != "") {
            $command = 'kill -9 ' . $this->cpcurrent_pid;
            shell_exec($command);
        }

        $data = [];
        $data['cpcurrent_pid'] = "";
        $data['cpcurrent_start'] = 0;
        if ($clearTick)
            $data['cpcurrent_tick'] = 0;
        $this->assign($data, true);

        //clear log
        //$this->_clearLog();
    }

    /**
     * @return \core\cpCronjob[]|false
     */
    public function getCronjobChilds()
    {
        $list = $this::get()->where('cpactive and f_cpcronjob_parent="' . $this->getId() . '"')->getList();
        if ($list->count() == 0)
            $list = false;
        return $list;
    }

    /**
     * @param string $sType
     * @param string|array $aText
     */
    public function sendMessage($sType, $aText)
    {
        if($this->cpignore_errors=="1")
            return;


        $sSubject="CRONJOB MANAGER ".$this->cptitle." - ".strtoupper($sType);

        //google message
        /*
        $NL="\n";
        $sText="";
        if(is_array($aText))
            $sText = implode($NL,$aText);
        else
            $sText = $aText;
        $sText = $sSubject.$NL.mb_substr($sText, 0,100).$NL."Please take a look at the logs";
        \App\Notifications\Channels\GoogleChat::message( \App\Notifications\Channels\GoogleChat::BASE_ERROR_REPORTING_ROOM, $sText );
        */

        //mail
        $NL="<br>\n\r";
        $sText="";
        if(is_array($aText))
            $sText = implode($NL,$aText);
        else
            $sText = nl2br($aText);
        $sText = '<html><body>'.$sSubject.$NL.mb_substr($sText, 0,100).$NL.'Please take a look at the logs</body></html>';

        $sTo = CRONJOB_MANAGER__MAIL_ADDRESS;
        if($sTo!="")
            $this->getConfig()->sendMail($sTo, $sSubject, $sText);

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
        $ret = false;
        //cronjob was started
        if (!$this->isRunning()) {
            if ($this->cpcurrent_pid != "") {

                //pid is present, means tick is finish
                $errorFilePath = $this->_getErrorFilePath();
                $outputFilePath = $this->_getOutputFilePath();
                $bHasError=false;
                if(file_exists($errorFilePath) && filesize($errorFilePath) > 0)
                {
                    $bHasError=true;
                    $aContent=[];
                    $aContent[] = file_get_contents($errorFilePath);
                    if($this->cpignore_errors=="1")
                    {
                        $aContent[]="Cronjob continue execution";
                    }
                    else
                    {
                        $aContent[]="Cronjob stop execution";
                    }
                    @unlink($errorFilePath);

                    $type = "error";
                    $this->writeLog($type, $aContent);
                }

                if ($this->cpignore_errors != "1" && $bHasError) {

                    //there is an error
                    @unlink($outputFilePath);
                    @unlink($this->_getFinishedFilePath());
                    @unlink($this->_getParamFilePath());
                    $this->kill();

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
                            $sSubject = 'Job output too large';
                            $sText = "Please review the following if you're responsible of this cronjb<br/>file size: $fileSize, job: " . $this->getId() ."/" . $this->cptitle . ", moved output file to $newName";
                            if($sTo!="")
                                $this->getConfig()->sendMail($sTo, $sSubject, $sText);

                        }

                        $memory_peak_usage = memory_get_peak_usage(true);
                        if ($memory_peak_usage > $this->_memorySoftLimit) {
                            $msg = 'Limit of ' . $this->_memorySoftLimit . ' has been surpassed<br/> Cj id: ' . $this->getId() . "<br/> Cj Name: " . $this->cptitle;

                            $sTo = CRONJOB_MANAGER__MAIL_ADDRESS;
                            $sSubject = 'Excessive memory usage';
                            if($sTo!="")
                                $this->getConfig()->sendMail($sTo, $sSubject, $msg);
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
                        $data['cpfinished_count'] = ($this->cpfinished_count + 1);
                        $data['cplast_end'] = date('Y-m-d H:i:s');
                        $data['cplast_ticks'] = $this->cpcurrent_tick;
                        $this->assign($data, true);

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
                    } else {
                        //not finished, restart with different parameters
                        $this->kill(false);
                        $this->_restart($wrapper_filepath, $aParams);
                        $ret = true;
                    }
                }
            } else {
                //start new
                if ($force || $this->_shouldStart()) {
                    //cronjob has to start
                    $this->_start($wrapper_filepath);
                    $ret = true;
                }
            }
        } else {
            //runs, but how long?
            $currentstart = strtotime($this->cpcurrent_start);
            $duration = time() - $currentstart;
            $minutes = floor($duration / 60);
            if ($minutes > $this->getMaxRuntime()) {
                //run longer than 10 minutes

                //write log
                $type = "runtime";
                $aContent=[];
                $aContent[] = "Reached the *{$this->getMaxRuntime()}* minutes limit for a tick.";
                if($this->shouldKillProcessAfterRuntime())
                    $aContent[]="Process kill by the cronjob manager";
                $this->writeLog($type, $aContent);

                if($this->shouldKillProcessAfterRuntime())
                {
                    //Copy the files to a folder where we can analyze to get clues of what happened
                    $timestamp = date( 'YmdHis' );
                    $path      = "cronjob/{$this->getId()}/{$timestamp}";

                    if ( file_exists( $this->_getErrorFilePath() ) ) {
                        $content = file_get_contents( $this->_getErrorFilePath() );
                        \Storage::put( "$path/error.log", $content );
                        unlink( $this->_getErrorFilePath() );
                    }

                    if ( file_exists( $this->_getOutputFilePath() ) ) {
                        $content = file_get_contents( $this->_getOutputFilePath() );
                        \Storage::put( "$path/output.log", $content );
                        unlink( $this->_getOutputFilePath() );
                    }

                    if ( file_exists( $this->_getFinishedFilePath() ) ) {
                        $content = file_get_contents( $this->_getFinishedFilePath() );
                        \Storage::put( "$path/finished.log", $content );
                        unlink( $this->_getFinishedFilePath() );
                    }

                    if ( file_exists( $this->_getParamFilePath() ) ) {
                        $content = file_get_contents( $this->_getParamFilePath() );
                        \Storage::put( "$path/param.log", $content );
                        unlink( $this->_getParamFilePath() );
                    }

                    $this->kill();
                }
            }
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
        /*
        $dDate = $this->getPreviousRunDate();
        if($dDate->getTimestamp() + 60 > time())
        {
            //should start within the last minute
            if(!$this->isRunning())
            {
                return true;
            }
        }
        return false;
        */

        if($this->isDue())
        {
            //should start within the last minute
            if(!$this->isRunning())
            {
                $start = false;

                $lastend = $this->cplast_end;
                //was cronjob start before?
                if($lastend=="" || $lastend=="0000-00-00 00:00:00")
                {
                    //if not, can start
                    $start = true;
                }
                else
                {
                    //is cronjob finish in this minute?
                    if(date('Y-m-d H:i:00',strtotime($lastend))!=date('Y-m-d H:i:00'))
                    {
                        //if not, than start
                        $start = true;
                    }
                }

                return $start;
            }
        }
        return false;
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
        $data['cplast_start'] = date("Y-m-d H:i:s");
        $data['cplast_end'] = 0;
        $data['cplast_ticks'] = 0;
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
    public function clearLog()
    {
        $sql="delete from cpcronjob_log where f_cpcronjob='".$this->getId()."' and date_add(cpcreated, interval ".$this->cpkeep_logging_in_days." day) < now()";
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
        $pid=0;

        $sCommandOutput = $this->_getOutputFilePath();
        $sCommandError = $this->_getErrorFilePath();

        $paramArray = $this->getCronjobParametersArray()
            + ($aParams ?: []); // mixing both groups of parameters into a single array

        $sParam = implode(' ', array_map(function($name, $value) {
            return escapeshellarg($name) . " " . escapeshellarg($value);
        }, array_keys($paramArray), $paramArray));

        // this is a normal job
        /*
        $sCommand = $this->getConfig()->getConfigParam('rs-cronjobmanager_cli_command');
        $sCommand = str_replace("#1#","$wrapper_filepath $this->cpid $sParam",$sCommand);
        $sCommand = str_replace("#2#", $sCommandOutput, $sCommand);
        $sCommand = str_replace("#3#", $sCommandError, $sCommand);
        */
        $sCommand = "nohup ".CRONJOB_MANAGER__PHP_INTERPRETER." -f $wrapper_filepath $this->cpid $sParam > $sCommandOutput 2> $sCommandError & echo $!";
        $sCommand = trim($sCommand);
        if($sCommand!="")
            $pid = shell_exec($sCommand);

        return $pid;
    }


    /**
     * @return bool|\DateTime
     * @throws \Exception
     */
    public function getNextRunDate()
    {
        $ret = false;
        if($aCronjobScheduling = $this->getCronjobSchedulings())
        {
            $min = false;
            foreach($aCronjobScheduling as $oCronjobScheduling)
            {
                $date = null;
                $nr=0;
                while(true)
                {
                    $date = $oCronjobScheduling->getNextRunDate($nr);
                    if($date==false)
                    {
                        //no date found in the future
                        break;
                    }
                    else
                    {
                        //date found, is it on holiday?
                        if($this->cprun_on_holiday==false)
                        {
                            //Get the holidays count of today
                            $holiday=$date->format('Y-m-d');
                            $sql_holidays = "select count(*) from rsholiday where holiday='$holiday'";
                            if($this->getConfig()->getScalar($sql_holidays)!="0")
                            {
                                //is on holiday
                                $date = null;
                            }
                        }
                    }

                    if($date==null)
                    {
                        //next date
                        $nr++;
                    }
                    else
                    {
                        //date found, take this
                        break;
                    }
                }

                if($date)
                {
                    if($min===false || $date->getTimestamp() < $min)
                    {
                        $min = $date->getTimestamp();
                    }
                }
            }

            //calculate the closest datetime
            if($min!==false)
            {
                $ret = new \DateTime();
                $ret->setTimestamp($min);
            }
        }

        return $ret;
    }


    /**
     * should run now?
     *
     * @return bool
     */
    public function isDue()
    {
        //is it on holiday?
        if($this->cprun_on_holiday==false)
        {
            //Get the holidays count of today
            $holiday=date('Y-m-d');
            $sql_holidays = "select count(*) from rsholiday where holiday='$holiday'";
            if($this->getConfig()->getScalar($sql_holidays)!="0")
            {
                return false;
            }
        }


        $ret = false;
        if($aCronjobScheduling = $this->getCronjobSchedulings())
        {
            foreach($aCronjobScheduling as $oCronjobScheduling)
            {
                if($oCronjobScheduling->isDue())
                {
                    $ret = true;
                    break;
                }
            }
        }

        return $ret;
    }

    /**
     * @return bool|\DateTime
     * @throws \Exception
     */
    public function getPreviousRunDate()
    {
        $ret = false;
        if($aCronjobScheduling = $this->getCronjobSchedulings())
        {
            $min = false;
            foreach($aCronjobScheduling as $oCronjobScheduling)
            {
                $date = null;
                $nr=0;
                while(true)
                {
                    $date = $oCronjobScheduling->getPerviousRunDate($nr);
                    if($date==false)
                    {
                        //no date found in the future
                        break;
                    }
                    else
                    {
                        //date found, is it on holiday?
                        if($this->cprun_on_holiday==false)
                        {
                            //Get the holidays count of today
                            $holiday=$date->format('Y-m-d');
                            $sql_holidays = "select count(*) from rsholiday where holiday='$holiday'";
                            if($this->getConfig()->getScalar($sql_holidays)!="0")
                            {
                                //is on holiday
                                $date = null;
                            }
                        }
                    }

                    if($date==null)
                    {
                        //next date
                        $nr++;
                    }
                    else
                    {
                        //date found, take this
                        break;
                    }
                }

                if($date)
                {
                    if($min===false || $date->getTimestamp() > $min)
                    {
                        $min = $date->getTimestamp();
                    }
                }
            }

            //calculate the closest datetime
            if($min!==false)
            {
                $ret = new \DateTime();
                $ret->setTimestamp($min);
            }
        }

        return $ret;
    }


    /**
     * @return \core\cpCronjobScheduling[]|false
     */
    public function getCronjobSchedulings()
    {
        /**
         * @var \core\cpCronjobScheduling[]|\core\inc\listing $list
         */
        $list = \core\cpCronjobScheduling::get()->where('f_cpcronjob="'.$this->getId().'" and cpactive')->getList();
        if($list->count()==0)
            $list = false;
        return $list;
    }


    private $cronjobParameters = null;
    /**
     * @return false|\core\inc\listing<cpCronjobParameter>
     */
    public function getCronjobParameters()
    {
        if ($this->cronjobParameters === null) {
            /**
             * @var \core\cpCronjobParameter[]|\core\inc\listing $list
             */
            $list = \core\cpCronjobParameter::get()->where('f_cpcronjob="' . $this->getId() . '" and cpactive')->orderBy('cpname')->getList();
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
        $parameters = $this->getCronjobParameters();
        $parameters = $parameters ? $parameters->getArray() : [];

        // first convert to a more manageable array
        $paramArray = array();
        /** @var cpCronjobParameter $param */
        foreach ($parameters as $param) {
            $paramArray[$param->cpname] = $param->cpvalue;
        }

        return $paramArray;
    }

    /**
     * Execute the cronjob now, if it is not running.
     *
     * @param       $cpid
     * @param array $paramsNextTime Used to define the parameters that should be sent on the next time the cronjob
     *                              is being executed.
     *
     * @return bool
     */
    public static function runASAP($cpid, $paramsNextTime = null)
    {
        $ret = false;
        if ($oCronjob = \core\cpCronjob::find($cpid)) {
            if (!$oCronjob->isRunning()) {
                $paramArray = $oCronjob->getCronjobParametersArray();
                $path = \core\cpCronjob::getCronjobScriptPath($paramArray);

                //cronjob has to start
                $oCronjob->_start($path, $paramsNextTime);
                $ret = true;
            }
        }
        return $ret;
    }

    /**
     * @param string $customId
     * @param null $paramsNextTime
     * @return bool
     */
    public static function runAsapByCustomId($customId, $paramsNextTime = null)
    {
        $oConfig = getConfig();
        $sql="select cpid from cpcronjob where cpcustom_id='".$oConfig->escapeString($customId)."'";
        $cpid=$oConfig->getScalar($sql);

        $ret = false;
        if($cpid!="")
        {
            $ret = self::runASAP($cpid,$paramsNextTime);
        }
        return $ret;
    }

    /**
     * @param string $customId
     * @return cpCronjob|false
     */
    public static function findByCustomId($customId)
    {
        $oConfig = getConfig();
        $sql="select cpid from cpcronjob where cpcustom_id='".$oConfig->escapeString($customId)."'";
        $cpid=$oConfig->getScalar($sql);

        $ret = false;
        if($cpid!="") {
            $ret = self::find($cpid);
        }
        return $ret;
    }

    /**
     * Count the number of ticks for the Cronjob.
     *
     * @return null|string
     */
    public function countTicks()
    {
        $sqlString = "select count(*) from cpcronjob_log where f_cpcronjob='{$this->getId()}'";

        return $this->getConfig()->getScalar($sqlString);
    }

    /**
     * @return bool|cpCronjobLog
     */
    public function getLastTick()
    {
        $sqlString = "SELECT cpid FROM cpcronjob_log WHERE f_cpcronjob = '{$this->getId()}' ORDER BY cpsort_order DESC";
        $index1 = $this->getConfig()->getScalar($sqlString);
        $cronjoblog = new cpCronjobLog($this->getConfig());
        $cronjoblog->load($index1);

        return $cronjoblog;
    }


    public static function getCronjobScriptPath($aParameters)
    {
        $basePath = __DIR__;

        $normalPath = realpath("$basePath/../cronjobmanager/cpcronjobcommandline.php");
        $queuePath = realpath("$basePath/../cronjobmanager/cpcronjobcommandline_queue.php");

        if (array_key_exists('cpcronjob_queue', $aParameters)) {
            $scriptToUse = $queuePath;
        } else {
            // this is a normal job
            $scriptToUse = $normalPath;
        }

        return $scriptToUse;
    }
}
