<?php
namespace MageCompatibility\Extension;

use MageCompatibility\Extension\Mage as Mage;
use Netresearch\Logger;

class Setup
{
    protected $_changes=array(
        'add' => array()
    );

    protected $_conn;

    public function __construct($file)
    {
        if (is_null($this->_conn)) {
            $this->_conn = new Setup\Connection();
        }
        $code = $this->_getNormalizedInstallerCode($file);
        eval('namespace ' . __NAMESPACE__ . ';?>' . $code);
    }

    /**
     * avoid usage of Magento core code in installer scripts, raise warning instead
     *
     * @param string $file Installer file name
     * @return string
     */
    protected function _getNormalizedInstallerCode($file)
    {
        $code = file_get_contents($file);
        /* replace direct instanciation */
        $code = preg_replace('/new [^(]+/', 'new Mage', $code);
        /* replace constant usage */
        $code = preg_replace('/[A-Za-z0-9_]+::[A-Za-z0-9_]+/', '0', $code);

        return $code;
    }

    public function startSetup()
    {
    }

    public function endSetup()
    {
    }

    public function run($queryClob)
    {
        $this->_evaluateQueries($queryClob);
    }

    public function getChanges()
    {
        return array_merge($this->_changes, $this->getConnection()->getChanges());
    }

    /**
     * get table name
     *
     * @param string $tableName Table name
     * @return string
     */
    public function getTable($tableName)
    {
        return $tableName;
    }

    public function getConnection()
    {
        return $this->_conn;
    }

    protected function _evaluateQueries($queryClob)
    {
        $queries = $this->_getQueries($queryClob);
        foreach ($queries as $query) {
            $query = trim($query);
            if (0 == strlen($query)) {
                continue;
            }
            preg_match('/CREATE TABLE( IF NOT EXISTS)? ([a-zA-Z0-9_]+) ?\((.+)\)/msi', $query, $matches);
            if (count($matches)) {
                $tableName   = $matches[2];
                $fieldDefinitions = explode(',', $matches[3]);
                $this->_addCreateTable($tableName, $fieldDefinitions);
            }
        }
    }

    protected function _addCreateTable($tableName, $fieldDefinitions)
    {
        foreach ($fieldDefinitions as $definition) {
            preg_match('/^`?([a-zA-Z0-9_]+)`? ([a-zA-Z]+)/ms', trim($definition), $nameMatches);
            if (0 < count($nameMatches)) {
                $fieldName = $nameMatches[1];
                $type = $nameMatches[2];
                if ('KEY' !== $type) {
                    $this->_addField($tableName, $fieldName);
                }
            }
        }
    }

    protected function _addField($table, $field)
    {
        if (false == array_key_exists($table, $this->_changes['add'])) {
            $this->_changes['add'][$table] = array();
        }
        $this->_changes['add'][$table][] = $field;
    }

    protected function _getQueries($rawQueries)
    {
        /* strip values to avoid wrong splitting if values contain ";" */
        $rawQueries = preg_replace('/".*"/U', '""', substr($rawQueries, 0, strlen($rawQueries)-1));
        $rawQueries = preg_replace('/\'.*\'/U', '\'\'', $rawQueries);

        return explode(';', $rawQueries);
    }


    /**
     * pseudo override, should be handeled
     * @todo
     */
    public function addAttribute()
    {}

    /**
     * pseudo override, should be handeled
     * @todo
     */
    public function updateAttribute()
    {}

    /**
     * pseudo override, should be handeled
     * @todo
     */
    public function updateCustomerForms()
    {}

    /**
     * pseudo override
     */
    public function __call($method, $args)
    {
        Logger::warning("Called Magento Setup method \"$method\" in installer script");
        return $this;
    }
}
