<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteLogPurger;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;

/**
 * Purges the log_visit, log_conversion and related tables of old visit data.
 *
 * Borrows heavily from PrivacyManager/LogDataPurger
 */
class SiteLogPurger extends \Piwik\Plugin
{
    /**
     * The max set of rows each table scan select should query at one time.
     */
    public static $selectSegmentSize = 100000;

    /**
     * Purges old data from the following tables:
     * - log_visit
     * - log_link_visit_action
     * - log_conversion
     * - log_conversion_item
     *
     * @param int $idSite              Site ID
     * @param int $deleteLogsOlderThan The number of days after which log entires are considered old.
     *                                 Visits and related data whose age is greater than this number
     *                                 will be purged.
     */
    public function purgeData($idSite, $deleteLogsOlderThan, $output)
    {
        $maxIdVisit = $this->getDeleteIdVisitOffset($deleteLogsOlderThan);

        // break if no ID was found (nothing to delete for given period)
        if (empty($maxIdVisit)) {
            $output->writeln('<info>SiteLogPurger: no data to purge</info>');
            return;
        }

        $logTables = self::getDeleteTableLogTables();

        // delete data from log tables
        $where = "WHERE idvisit <= ? AND idsite = ?";
        foreach ($logTables as $logTable) {
            $output->writeln("<info>SiteLogPurger: purging $logTable</info>");
            Db::deleteAllRows($logTable, $where, "idvisit ASC", 10000, array($maxIdVisit, $idSite));
        }

        // optimize table overhead after deletion
        $output->writeln("<info>SiteLogPurger: optimizing tables</info>");
        Db::optimizeTables($logTables);
        $output->writeln("<info>SiteLogPurger: operations completed</info>");
    }

    /**
     * get highest idVisit to delete rows from
     * @return string
     */
    private function getDeleteIdVisitOffset($deleteLogsOlderThan)
    {
        $logVisit = Common::prefixTable("log_visit");

        // get max idvisit
        $maxIdVisit = Db::fetchOne("SELECT MAX(idvisit) FROM $logVisit");
        if (empty($maxIdVisit)) {
            return false;
        }

        // select highest idvisit to delete from
        $dateStart = Date::factory("today")->subDay($deleteLogsOlderThan);
        $sql = "SELECT idvisit
		          FROM $logVisit
		         WHERE '" . $dateStart->toString('Y-m-d H:i:s') . "' > visit_last_action_time
		           AND idvisit <= ?
		           AND idvisit > ?
		      ORDER BY idvisit DESC
		         LIMIT 1";

        return Db::segmentedFetchFirst($sql, $maxIdVisit, 0, -self::$selectSegmentSize);
    }

    // let's hardcode, since these are not dynamically created tables
    public static function getDeleteTableLogTables()
    {
        $result = Common::prefixTables('log_conversion',
            'log_link_visit_action',
            'log_visit',
            'log_conversion_item');
        return $result;
    }
}
