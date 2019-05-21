<?php

namespace ActiveORM;

/**
 * Represents a block of SQL conditions.
 * @author Edu
 */
class ConditionBlock
{
    protected $conditions;
    protected $operator;

    public function __construct($conditions, $operator = 'AND')
    {
        $this->conditions = is_string($conditions) ?
            [ $conditions ] :
            $conditions;
        $this->operator = $operator;
    }

    public function __toString()
    {
        return '(' .
            implode(
                " {$this->operator} ",
                $this->parse($this->conditions)
            ) .
            ')';
    }

    /**
     * Converts $conditions into an associative array of conditions
     * @param string|array $conditions
     */
    protected function parse($conditions, $operator = 'AND')
    {
        $result = [];

        if(\is_numeric($conditions)){
            $result = [ sprintf("id = %d", $conditions) ];
        } else if(is_array($conditions)){
            foreach($conditions as $key => $value){
                if(\is_numeric($key)){
                    if(\is_numeric($value)){
                        $result[] = sprintf("id = %d", $value);
                    } else if(is_string($value)){
                        $result[] = $value;
                    } else if(is_array($value) && count($value) == 3){
                        //caso [ columna, operador, valorDeseado ]
                        $result[] = sprintf("%s %s '%s'", ...$value);
                    } else {
                        throw new \Exception("Unexpected condition format");
                    }
                } else if(is_string($key)) {
                    if(is_array($value)){
                        $result[] = sprintf(
                            "%s IN(%s)",
                            $key,
                            implode(
                                ', ',
                                array_map(
                                    function($e){
                                        if(\is_numeric($e)){
                                            return $e;
                                        }
                                        return "'{$e}'";
                                    },
                                    $value
                                )
                            )
                        );
                    } else {
                        $result[] = sprintf("%s = '%s'", $key, $value);
                    }
                } else {
                    throw new \Exception("Not implemented 2!");
                }
            }
        }

        return $result;
    }

    /**
     * Adds new conditions to the existing ones
     */
    public function append($conditions)
    {
        if(is_string($conditions)){
            $conditions = [ $conditions ];
        }
        $this->conditions = array_merge($this->conditions, $conditions);
    }
}