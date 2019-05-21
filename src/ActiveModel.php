<?php

namespace ActiveORM;

abstract class ActiveModel implements HydratableInterface
{
    private $_cachedGetters = [];

    public static function getTableName()
    {
        return strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/', '_$0',
                (new \ReflectionClass(static::class))->getShortName()
            )
        );
    }

    public static function beforeFind(ActiveQuery $query)
    {
        return $query;
    }

    public function getPrimaryKeyValue()
    {
        return $this->id;
    }

    public static function find($conditions = null)
    {
        $tableName = static::getTableName();

        return (new ActiveQuery(static::class))
            ->where($conditions);
    }

    public static function findOne($conditions)
    {
        return self::find($conditions)->fetchOne();
    }

    public function hydrate($data)
    {
        $hydratable = $this->getHydratableFields();
        $ignore = $this->getIgnoreFields();

        foreach($data as $key => $value){
            if(
                ($hydratable == null || count($hydratable) == 0 || in_array($key, $hydratable)) &&
                ($ignore == null || count($ignore) == 0 || !in_array($key, $ignore))
            ){
                $this->$key = $value;
            }
        }

        $this->afterHydrate();
    }

    public function getHydratableFields()
    {
        return null;
    }

    public function getIgnoreFields()
    {
        return null;
    }

    protected function afterHydrate()
    {
        
    }

    /**
     * Repopulates the model from the database
     */
    public function refresh()
    {
        $this->hydrate(
            (new Query)->from(static::getTableName())
            ->where(['id' => $this->id])
            ->fetchOne()
        );
    }

    /**
     * @param string $relatedModelClass
     */
    public function hasOne($relatedModelClass, $foreignKey = null, $localKey = 'id')
    {
        return new RelationQuery(
            static::class,
            $relatedModelClass,
            false,
            true,
            $foreignKey,
            $localKey
        );
    }

    public function belongsTo($relatedModelClass, $foreignKey = null, $localKey = 'id')
    {
        return new RelationQuery(
            static::class,
            $relatedModelClass,
            false,
            false,
            $foreignKey,
            $localKey
        );
    }

    /**
     * @param string $relatedClass
     * @param string $foreignKey Name of the foreign key field (in the related model table)
     * that points to this model.
     */
    public function hasMany($relatedClass, $foreignKey = null, $localKey = 'id')
    {
        return new RelationQuery(
            static::class,
            $relatedClass,
            true,
            true,
            $foreignKey,
            $localKey
        );
    }

    /*protected function pickForeignKeyField($class = null)
    {
        return strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/', '_$0',
                basename(
                    $class !== null ?
                    $class :
                    static::class
                )
            )
        ).'_id';
    }*/

    public function __get($fieldName)
    {
        if($fieldName == ''){
            throw new \Exception('Trying to get empty field!!!');
        }
        if(isset($this->$fieldName) || method_exists($this, $fieldName)){
            return $this->$fieldName;
        } else if(method_exists($this, $getter = "get".ucfirst($fieldName))){
            if(!isset($this->_cachedGetters[$fieldName])){
                $value = $this->$getter();
                if($value instanceof RelationQuery){
                    $relation = $value;
                    $localField = $relation->getKeyFieldOnParentTable();
                    $value = $relation->where([
                        $relation->getKeyFieldOnChildTable() => $this->$localField
                    ])->fetch();
                }
                $this->_cachedGetters[$fieldName] = $value;
            }

            return $this->_cachedGetters[$fieldName];
        } else {
            throw new \Exception("Field '$fieldName' doesn't exists!");
        }
    }
}