<?php
namespace SourceCodeComplexity;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;

class SourceCodeComplexity implements JudgePlugin
{
    protected $config;
    protected $extensionPath;
    protected $settings;
    protected $results;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->name   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->name};
    }

    /**
     *
     * @param string $extensionPath the path to the extension to check
     * @return float the sum of scores of all tests
     */
    public function execute($extensionPath)
    {
        $score = 0;
        if ($this->settings->phpDepend->enabled) {
            $score = $score + $this->executePHPDepend($extensionPath);
        }
        if ($this->settings->phpcpd->enabled) {
            $score = $score + $this->executePHPCpd($extensionPath);
        }
        if ($this->settings->phpMessDetector->enabled) {
            $score = $score + $this->executePHPMessDetector($extensionPath);
        }
        Logger::setScore($extensionPath, $this->name, $score);
        return $score;
    }

    /**
     * checks the extension with phpMessDetector and returns the scoring
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after php mess detector test
     */
    protected function executePHPMessDetector($extensionPath)
    {
        $executable = 'vendor/phpmd/phpmd/src/bin/phpmd';
        $score = $this->settings->phpMessDetector->good;
        $mdResults = array();
        exec(sprintf($executable . ' "%s" "%s" "%s"', $extensionPath, 'text', $this->settings->phpMessDetector->useRuleSets), $mdResults);
        if ($this->settings->phpMessDetector->allowedIssues < count($mdResults)) {
            $score = $this->settings->phpMessDetector->bad;
            foreach ($mdResults as $issue) {
                //prepare comment for db log
                $comment = null;
                $linenumber = null;
                $filename = null;
                $commentParts = explode(":", $issue, 2);
                if (count($commentParts) > 1) {
                    $filename = $commentParts[0];
                    $comment = $commentParts[1];
                }

                $commentParts = explode("\t", $comment, 2);
                if (count($commentParts) > 1)
                {
                    $linenumber = $commentParts[0];
                    $comment = $commentParts[1];
                }

                IssueHandler::addIssue(new Issue(
                        array(  "extension"     =>  $extensionPath,
                                "checkname"     =>  $this->name,
                                "type"          =>  'mess_detector',
                                "comment"       =>  $comment,
                                "linenumber"    =>  $linenumber,
                                "files"         =>  array($filename),
                                "failed"        =>  true)));
            }
        }
        return $score;
    }

    /**
     * checks the extensions complexity with phpDepend and returns the scoring
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after php depend test
     */
    protected function executePHPDepend($extensionPath)
    {
        $executable = 'vendor/pdepend/pdepend/src/bin/pdepend';
        $metricViolations = 0;
        $tempXml = $this->settings->phpDepend->tmpXmlFilename;
        $usedMetrics = $this->settings->phpDepend->useMetrics->toArray();
        $command = sprintf($executable . ' --summary-xml="%s" "%s"', $tempXml, $extensionPath);
        exec($command);
        $metrics = current(simplexml_load_file($tempXml));
        Logger::setResultValue($extensionPath, $this->name, 'metrics', $metrics);
        foreach ($metrics as $metricName => $metricValue) {
            if (in_array($metricName, $usedMetrics)
                && $this->settings->phpDepend->{$metricName} < $metricValue) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => $metricName,
                                "comment"   => $metricValue,
                                "failed"        =>  true)));  
                
                ++ $metricViolations;
            }
        }
        $score = $this->settings->phpDepend->metricViolations->good;
        if ($this->settings->phpDepend->metricViolations->allowedMetricViolations < $metricViolations) {
            $score = $score + $this->settings->phpDepend->metricViolations->bad;
        }
        unlink($tempXml);
        return $score;
    }

    /**
     *  checks the extension with php copy and paste detector
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after phpcpd test
     */
    protected function executePHPCpd($extensionPath)
    {
        $minLines   = $this->settings->phpcpd->minLines;
        $minTokens  = $this->settings->phpcpd->minTokens;
        $verbose = null;
        $suffixes = '';
        $exclude  = array();
        $commonPath = false;

        $facade = new \File_Iterator_Facade;
        $files = $facade->getFilesAsArray(
            $extensionPath, $suffixes, array(), $exclude, $commonPath
        );

        $strategy = new \PHPCPD_Detector_Strategy_Default;
        
        $detector = new \PHPCPD_Detector($strategy, $verbose);

        $clones = $detector->copyPasteDetection(
          $files, $minLines, $minTokens
        );

        $cpdPercentage = $clones->getPercentage();

        if ($this->settings->phpcpd->percentageGood < $cpdPercentage) {
            IssueHandler::addIssue(new Issue(
                    array(  "extension" =>  $extensionPath,
                            "checkname" => $this->name,
                            "type"      => 'duplicated_code',
                            "comment"   => $cpdPercentage,
                            "failed"        =>  true)));
            
            return $this->settings->phpcpd->bad;
        }
        return $this->settings->phpcpd->good;
    }
}
