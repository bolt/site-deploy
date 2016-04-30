<?php

namespace Bolt\Deploy\Action;

use Bolt\Deploy\Config\Config;
use Bolt\Deploy\Config\Site;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Permission & ACL action class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SetPermissions implements ActionInterface
{
    /** @var Config */
    protected $config;
    /** @var Site */
    protected $siteConfig;

    /**
     * Constructor.
     *
     * @param Site $siteConfig
     */
    public function __construct(Config $config, Site $siteConfig)
    {
        $this->config = $config;
        $this->siteConfig = $siteConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $output = new ConsoleOutput();
        $sitePath = $this->siteConfig->getPath('site');
        $hasUser = $this->userExists($output, $this->config->getPermission('user'));
        $hasGroup = $this->groupExists($output, $this->config->getPermission('group'));
        if ($hasUser && $hasGroup) {
            $chown = sprintf(
                'chown -R %s:%s %s',
                $this->config->getPermission('user'),
                $this->config->getPermission('group'),
                $sitePath
            );
            exec($chown);
        }

        $setfacls = [];
        foreach ($this->config->getAcl('users') as $user) {
            if ($this->userExists($output, $user)) {
                $setfacls[] = sprintf('-m u:%s:rwX', $user);
            }
        }
        foreach ($this->config->getAcl('groups') as $group) {
            if ($this->groupExists($output, $group)) {
                $setfacls[] = sprintf('-m g:%s:rwX', $group);
            }
        }

        $setfacl = sprintf('%s %s', implode(' ', $setfacls), $sitePath);
        exec('setfacl -R ' . $setfacl);
        exec('setfacl -dR ' . $setfacl);
    }

    private function userExists(OutputInterface $output, $user)
    {
        exec(sprintf('id -u %s 2>&1 > /dev/null', $user), $cmdOutput, $cmdReturn);
        if ($cmdReturn !== 0) {
            $output->writeln(sprintf('<error>User name "%s" does not exist!</error>', $user));

            return false;
        }

        return true;
    }

    private function groupExists(OutputInterface $output, $group)
    {
        exec(sprintf('id -g %s 2>&1 > /dev/null', $group), $cmdOutput, $cmdReturn);
        if ($cmdReturn !== 0) {
            $output->writeln(sprintf('<error>Group name "%s" does not exist!</error>', $group));

            return false;
        }

        return true;
    }
}