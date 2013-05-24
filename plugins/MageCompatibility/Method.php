<?php
namespace MageCompatibility;

class Method extends Tag
{
    const TYPE_MIXED   = 'mixed';
    const TYPE_UNKNOWN = 'unknown';
    const TYPE_INT     = 'int';
    const TYPE_BOOL    = 'bool';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_FLOAT   = 'float';
    const TYPE_ARRAY   = 'array';
    const TYPE_STRING  = 'string';

    protected $_shortTagType = 'm';
    protected $_tagType      = 'method';
    protected $_table        = 'methods';

    protected $_name;
    protected $_params=array();
    protected $_context=array();

    public function __construct($name, $params, $context)
    {
        $this->setName($name);
        $this->setParams($params);
        $this->setContext($context);
    }

    protected function _getTableName()
    {
        return self::TABLE;
    }

    public function setName($name)
    {
        $this->_name = $name;
    }

    protected function _getFieldsToSelect()
    {
        $fields = parent::_getFieldsToSelect();
        $fields[] = 'class_id';
        $fields[] = 'visibility';
        $fields[] = 'required_params_count';
        $fields[] = 'optional_params_count';
        $fields[] = 'params';
        return $fields;
    }

    public function setParams($params)
    {
        $this->_params = $params;
    }

    public function setContext($context)
    {
        if (false == is_array($context)) {
            $context = $this->_getContextArray($context);
        }
        $this->_context = $context;
    }

    /**
     * validate param type for {$offset}th param
     * !!!will return true if we are not sure about the type!!!
     *
     * @param int    $offset Param offset
     * @param string $type   Doctype styled type
     * @return bool
     */
    public function isParamType($offset, $expectedType)
    {
        $param = $this->_params[$offset];
        $value = $param->value;
        $type  = self::TYPE_MIXED;
        if ($param->value instanceof \PHPParser_Node_Expr_MethodCall) {
            $method = new Method($param->value->name, $param->value->args, null);
            $type = $method->getReturnType();
        } elseif ($param->value instanceof \PHPParser_Node_Scalar_String
            || $param->value instanceof \PHPParser_Node_Scalar_ClassConst
            || $param->value instanceof \PHPParser_Node_Scalar_DirConst
            || $param->value instanceof \PHPParser_Node_Scalar_FileConst
            || $param->value instanceof \PHPParser_Node_Scalar_FuncConst
            || $param->value instanceof \PHPParser_Node_Scalar_LineConst
            || $param->value instanceof \PHPParser_Node_Scalar_MethodConst
            || $param->value instanceof \PHPParser_Node_Scalar_NSConst
            || $param->value instanceof \PHPParser_Node_Scalar_TraitConst
        ) {
            $type = self::TYPE_STRING;
        } elseif ($param->value instanceof \PHPParser_Node_Scalar_DNumber) {
            $type = self::TYPE_FLOAT;
        } elseif ($param->value instanceof \PHPParser_Node_Scalar_LNumber) {
            $type = self::TYPE_INT;
        } elseif ($param->value instanceof \PHPParser_Node_Expr_Array) {
            $type = self::TYPE_FLOAT;
        }
        if (in_array($expectedType, array(self::TYPE_UNKNOWN, self::TYPE_MIXED))) {
            return true;
        }
        return ($type == $expectedType);
    }

    public function isExtensionMethod($name, $extensionPath)
    {
        $token = 'function ' . $name;
        $command = 'grep -rEl "' . $token . '" ' . $extensionPath . '/app';
        exec($command, $filesWithThatToken, $return);
        return (0 < count($filesWithThatToken));
    }

    /**
     * examine return type of the method
     *
     * @return string
     */
    public function getReturnType()
    {
        return self::TYPE_MIXED;
    }

    /**
     * getContextArray
     *
     * @TODO
     * @param mixed $contextString
     * @return void
     */
    protected function _getContextArray($contextString)
    {
        return array();
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getParams()
    {
        return $this->_params;
    }

    public function getContext($key=null)
    {
        if (is_null($key)) {
            return $this->_context;
        }
        return $this->_context[$key];
    }

    /**
     * if method is used in given context
     *
     * @TODO
     * @param array $contextTokens
     * @return void
     */
    public function isInContext($contextTokens)
    {
        return true;
    }

    /**
     * determine signatures matching the given param count
     *
     * @param array $candidates Array of DibiRows
     * @return array
     */
    protected function _filterByParamCount($candidates)
    {
        $infiniteParamsMethods = array(
            'Mage_Core_Helper_Abstract' => array(
                '__'
            )
        );
        foreach ($infiniteParamsMethods as $class=>$methods) {
            if (in_array($this->getName(), $methods)) {
                return $candidates;
            }
        }
        foreach ($candidates as $key => $candidate) {
            $givenParamsCount = count($this->_params);
            $minParamsCount = $candidate->required_params_count;
            $maxParamsCount = $candidate->required_params_count + $candidate->optional_params_count;
            if ($givenParamsCount < $minParamsCount
                || $maxParamsCount < $givenParamsCount
            ) {
                unset($candidates[$key]);
            }
        }
        return $candidates;
    }

    /**
     * determine signatures with the same context
     * currently we only check if the class we detected matches the signature
     *
     * @param array $candidates Array of DibiRows
     * @return array
     */
    protected function _filterByContext($candidates)
    {
        if (false === array_key_exists('class', $this->_context)
            && 0 < strlen($this->_context['class'])
            && Method::TYPE_MIXED !== $this->_context['class']
            && 0 < count($candidates)
        ) {
            $classIds = array();
            $query = 'SELECT name, id FROM [classes] WHERE id IN (%s) AND name=%s';
            foreach ($candidates as $key=>$candidate) {
                if ($candidate->class_id) {
                    $classIds[$key] = $candidate->class_id;
                }
            }
            try {
                $result = dibi::fetchPairs(
                    $query,
                    $classIds,
                    $this->_context['class']
                );
            } catch (\DibiDriverException $e) {
                dibi::test(
                    $query,
                    $classIds,
                    $this->_context['class']
                );
                throw $e;
            }
            $contextMatchingCandidates = $candidates;
            foreach ($candidates as $key=>$candidate) {
                if (false == in_array($candidate->class_id, $classIds)) {
                    unset($contextMatchingCandidates[$key]);
                }
            }
            return count($contextMatchingCandidates) ? $contextMatchingCandidates : $candidates;
        }
        return $candidates;
    }
}
