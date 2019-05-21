<?php

namespace ActiveORM;

/**
 * QueryBuilder user to query models related to other model
 */
class RelationQuery extends ActiveQuery
{
    protected $parentClass;
    protected $foreignKey; // Foreign Key referencing the parent model
    protected $localKey; // Foreign Key referencing the child(related) model
    protected $foreignKeyIsOnChildTable;
    protected $viaTable;

    /**
     * @param string $parentClass Classname of the parent (referencing) model
     * @param string $childClass Classname of the child (referenced) model
     * @param bool $returnsMany Whether the parent references many models (true), or just one (false)
     * @param bool  $foreignKeyIsOnChildTable Where is the foreign key?
     */
    public function __construct(
        $parentClass,
        $childClass,
        $returnsMany,
        $foreignKeyIsOnChildTable,
        $foreignKey = null,
        $localKey = 'id',
        ?\PDO $pdo = null
    ) {
        parent::__construct($childClass, $pdo);
        $this->parentClass = $parentClass;
        $this->returnsMany = $returnsMany;
        $this->foreignKeyIsOnChildTable = $foreignKeyIsOnChildTable;
        $this->foreignKey = (
            $foreignKey !== null ?
            $foreignKey :
            $this->deduceForeignKey($parentClass, $childClass, $foreignKeyIsOnChildTable)
        );
        $this->localKey = $localKey;
        $this->pdo = $pdo;
    }

    protected function deduceForeignKey($parentClass, $childClass, $foreignKeyIsOnChildTable)
    {
        if($foreignKeyIsOnChildTable){
            return $parentClass::getTableName().'_id';
        }

        return $childClass::getTableName().'_id';
    }

    /*public function getForeignKey()
    {
        return $this->foreignKey;
    }*/

    public function getKeyFieldOnParentTable()
    {
        if($this->foreignKeyIsOnChildTable){
            return $this->localKey;
        }

        return $this->foreignKey;
    }

    public function getKeyFieldOnChildTable()
    {
        $prefix = ( $this->viaTable ? "{$this->viaTable}." : "" );

        if($this->foreignKeyIsOnChildTable){
            return "{$prefix}{$this->foreignKey}";
        }

        return "{$prefix}{$this->localKey}";
    }

    /**
     * Gets the projected fields on the children object that will allow the ActiveQuery class
     * to relate the queried children to their correct parent
     */
    public function getKeyFieldOnChildrenObjects()
    {
        $result = (
            $this->viaTable && $this->foreignKeyIsOnChildTable === false ? //case "belongsTo" relation with "viaTable"
            $this->foreignKey :
            $this->getKeyFieldOnChildTable()
        );

        if(($dot = strpos($result,'.')) !== false){
            $result = \substr($result, $dot + 1);
        }

        return $result;
    }

    /**
     * 
     */
    public function viaTable($tableName, $params = null)
    {
        $tableName = explode(' ', trim($tableName))[0];
        $this->viaTable = $tableName;
        $parentFK = $tableName.'.'.$this->parentClass::getTableName().'_id';
        $childFK = $tableName.'.'.$this->guessTableName($this->entity).'_id';
        if($params !== null){
            foreach($params as $field => $class){
                if( $this->sameOrChildOf($class, $this->parentClass) ){
                    $parentFK = "$tableName.$field";
                } else if ( $this->sameOrChildOf($class, $this->entity) ){
                    $childFK = "$tableName.$field";
                }
            }
        }

        $select = $parentFK;

        if($this->foreignKeyIsOnChildTable === false){
            //foreign key is on PARENT table ("belongsTo" relation),
            //but the relation is done "viaTable". We need to project the foreign key
            if($this->foreignKey === null){
                throw new \Exception("'belongsTo' relations 'viaTable' must explicitly provide the foreign key field");
            }
            $select = "{$parentFK} AS {$this->foreignKey}";
        }

        //The foreign key is not on the child table anyways
        //$this->foreignKeyIsOnChildTable = false;
        
        return $this->select($select)//("{$parentFK} AS {$this->getKeyFieldOnParentTable()}")
            ->join(
            $tableName,
            sprintf("%s = %s.%s",
                $childFK,
                $this->entity::getTableName(),
                'id'
            )
        );
    }

    protected function sameOrChildOf($class, $sameOrChildClass)
    {
        while($sameOrChildClass !== false && $class !== $sameOrChildClass){
            $sameOrChildClass = get_parent_class($sameOrChildClass);
        }

        return ($class == $sameOrChildClass);
    }
}