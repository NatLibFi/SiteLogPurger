<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteLogPurger\Commands;

use Piwik\Site;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SiteLogPurger command
 */
class PurgeSiteLogs extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('sitelogpurger:purge-site-logs');
        $this->setDescription('Purge logs of a site');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Site ID to purge');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Days to keep');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $idSite = $input->getOption('idsite');
        $days = $input->getOption('days');

        if (!Site::getSite($idSite)) {
            throw new \InvalidArgumentException('idsite is not a valid, no such site found');
        }
        if (!preg_match('/^\d+$/', $days)) {
            throw new \InvalidArgumentException('Invalid value for days given');
        }

        $output->writeln("<info>PurgeSiteLogs: idsite: $idSite, days to keep: $days</info>");

        $purger = new \Piwik\Plugins\SiteLogPurger\SiteLogPurger();
        $purger->purgeData($idSite, $days, $output);
    }
}
