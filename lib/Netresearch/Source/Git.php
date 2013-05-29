<?php
namespace Netresearch\Source;

use Netresearch\Logger;
use Netresearch\Source\SourceInterface;
use Netresearch\Source\Base as Source;

use \Exception as Exception;

/**
 * Git Handler for judge
 */
class Git extends Source implements SourceInterface
{
    const GIT_DEFAULT_BRANCH = 'master';

    /**
     * @see SourceInterface::copy()
     */
    public function copy($target, $branch = self::GIT_DEFAULT_BRANCH)
    {
        if (!Source::isGitRepo($this->_source)) {
            throw new Exception('Provided source is not a Git repository: ' . $this->_source);
        }

        $this->_cloneRepository($this->_source, $target);

        if ((null !== $branch) && (self::GIT_DEFAULT_BRANCH !== $branch)) {
            $this->_checkout($target, $branch);
        }
    }

    /**
     * Clone git repo to desired location
     */
    protected function _cloneRepository($repoUrl, $targetPath)
    {
        Logger::comment('Cloning Git repository');

        $command = sprintf('git clone %s %s 2>&1', $repoUrl, $targetPath);
        Logger::log($command);
        exec($command, $result, $return);

        if (0 !== $return) {
            throw new Exception(implode(PHP_EOL, $result));
        }
    }

    protected function _checkout($targetPath, $branch)
    {
        Logger::log('Git checkout %s', array($branch));

        $command = sprintf('cd %s; git checkout %s 2>&1; cd -', $targetPath, $branch);
        exec($command, $result, $return);

        if (0 !== $return) {
            throw new Exception(implode(PHP_EOL, $result));
        }
    }
}
