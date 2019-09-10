<?php
namespace core;

//require_once __DIR__. "/../inc/cronexpression/CronExpression.php";

/**
 * Class cpCronjobScheduling
 *
 * @property bool   $cpactive
 * @property string $cpcreated
 * @property string $cpid
 * @property string $cpscheduling
 * @property string $f_cpcronjob
 *
 * @package core
 */
class cpCronjobScheduling extends \core\inc\base
{
    public function __construct(\cconfig3 $oconfig)
    {
        parent::__construct("cpcronjob_scheduling", "cpid", $oconfig, null);
    }

    /**
     * Determine if the cron is due to run based on the current date or a
     * specific date.  This method assumes that the current number of
     * seconds are irrelevant, and should be called once per minute.
     *
     * @param string|\DateTime $currentTime Relative calculation date
     *
     * @return bool Returns TRUE if the cron is due to run or FALSE if not
     */
    public function isDue()
    {
        $ret = cpCronjobScheduling::calculateDatetime($this->cpscheduling);
        return $ret;
    }

    /**
     * @param int              $nth              Number of matches to skip before returning a
     *                                           matching next run date.  0, the default, will return the current
     *                                           date and time if the next run date falls on the current date and
     *                                           time.  Setting this value to 1 will skip the first match and go to
     *                                           the second match.  Setting this value to 2 will skip the first 2
     *                                           matches and so on.
     * @return false|\DateTime
     */
    public function getNextRunDate($nth=0)
    {
        $ret = cpCronjobScheduling::calculateDatetime($this->cpscheduling, $nth + 1);
        return $ret;
    }


    /**
     * @param int              $nth              Number of matches to skip before returning a
     *                                           matching next run date.  0, the default, will return the current
     *                                           date and time if the next run date falls on the current date and
     *                                           time.  Setting this value to 1 will skip the first match and go to
     *                                           the second match.  Setting this value to 2 will skip the first 2
     *                                           matches and so on.
     * @return false|\DateTime
     */
    public function getPerviousRunDate($nth=0)
    {
        $ret = cpCronjobScheduling::calculateDatetime($this->cpscheduling, ($nth + 1) * -1);
        return $ret;
    }


    /**
     * @param string $sScheduling
     * @param int    $nth
     *
     * @return false|\DateTime
     */
    public static function calculateDatetime($sScheduling, $nth = 0)
    {
        $ret = false;

        $sScheduling=trim($sScheduling);

        if ($sScheduling != "") {
            $aPart = explode(" ", $sScheduling);
            $aPart = array_map('trim', $aPart);
            //remove empty

            //$aPart = array_filter($aPart);

            $bFirstNotHoliday = false;
            if (strpos($aPart[2], "H") !== false) {
                $bFirstNotHoliday = true;
                $aPart[2] = str_replace("H", "W", $aPart[2]);
            }
            $sScheduling = implode(" ", $aPart);

            //echo $sScheduling."<br>";
            try {
                $cron = \Cron\CronExpression::factory($sScheduling);

                if ($nth == 0) {
                    $ret = $cron->isDue();
                } elseif ($nth < 0) {
                    $nth*=-1;
                    $cron->isDue();
                    $ret = $cron->getPreviousRunDate(null, ($nth - 1));
                } elseif ($nth > 0) {
                    $cron->isDue();
                    $ret = $cron->getNextRunDate(null, ($nth - 1));
                }
            } catch (\Exception $e) {
                //echo $e->getMessage();
                $ret = false;
            }

            if($ret!==false && $bFirstNotHoliday) {
                /**
                 * @var \DateTime $ret
                 */
                for($x=0;$x<10;$x++)
                {
                    if($ret->format('w')==0 || $ret->format('w')==6)
                    {
                        $ret->add(new \DateInterval('P1D'));
                    }
                    else
                    {
                        //test if holiday
                        $holiday=$ret->format('Y-m-d');
                        $sql_holidays = "select count(*) from cpholiday where holiday='$holiday'";
                        if(getConfig()->getScalar($sql_holidays)!="0")
                        {
                            $ret->add(new \DateInterval('P1D'));
                        }
                    }
                }
            }
        }

        return $ret;
    }

}