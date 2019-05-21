<?php

namespace ActiveORM;


class ActiveQuery extends Query
{
    protected $returnsMany = true;
    protected $with = [];
    protected $withQueries;

    /**
     * @param string $class The fully namespaced name of a class that extends BaseModel
     */
    public function __construct($class, ?\PDO $pdo = null)
    {
        parent::__construct($pdo);
        $tableName = $class::getTableName();
        $this->select("$tableName.*", true)
            ->from($tableName)
            ->into($class);
        //$this->entity = $class;
    }

    /**
     * @param string|array $relations Relations to eager-load
     */
    public function with($relations)
    {
        if($relations !== null){
            $this->with = array_merge(
                $this->with,
                ( is_string($relations) ? [ $relations ] : $relations )
            );
        }

        return $this;
    }

    public function fetch()
    {
        if($this->returnsMany){
            return parent::fetchAll();
        } else {
            return parent::fetchOne();
        }
    }

    public function one()
    {
        return $this->fetchOne();
    }

    public function all()
    {
        return $this->fetchAll();
    }

    /**
     * Called by Query right after one single model has been hydrated
     */
    protected function afterModelHydrated($model)
    {
        if($this->with !== null){
            foreach($this->with as $key => $value){
                if(is_numeric($key)){
                    $relationName = $value;
                    $callable = null;
                } else if(is_string($key) && is_callable($value)){
                    $relationName = $key;
                    $callable = $value;
                } else {
                    throw new \Exception("Unexpected relation definition");
                }
                    
                if( ($firstDot = strpos($relationName, '.')) !== false ){
                    $relationName = substr($relationName, 0, $firstDot);
                    $pendingRelations = \substr($value, $firstDot + 1);
                    if(preg_match('/^\[(.+)\]$/', $pendingRelations, $matches)){
                        if($callable !== null){
                            throw new \Exception("Callables not supported when using [] syntax in the relation definition");
                        }
                        $pendingRelations = \explode(',', $matches[1]);
                    } else if($callable !== null){
                        //pass the callable ahead
                        $pendingRelations = [ $pendingRelations => $callable ];
                        $callable = null;
                    }
                } else {
                    $pendingRelations = null;
                }
                if(!isset($this->withQueries[$relationName])){
                    $getter = 'get'.ucfirst($relationName);
                    $this->withQueries[$relationName] = (object) [
                        'query' => $model->$getter()->with($pendingRelations),
                        'callable' => $callable,
                        'ids' => new \Ds\Set(),
                        'fetched' => false,
                    ];
                }

                $relation = $this->withQueries[$relationName];
                $idField = $relation->query->getKeyFieldOnParentTable();
                //echo "Agregando a listado (campo clave: $idField): {$model->$idField}\n";
                $relation->ids->add($model->$idField);
            }
        }
    }

    /**
     * Called by Query after all fetched models have been fetched and hydrated
     */
    protected function afterAllFetchedAndHydrated($result)
    {
        if($this->withQueries != null){
            foreach($this->withQueries as $relationName => $relationParams){
                if(!$relationParams->fetched){

                    /*if($relationParams->query == null){
                        echo "relationParams->query ES NULL para relacion '$relationName'!!!\n";
                        continue;
                    }*/

                    $keyFieldOnChildModel = $relationParams->query->getKeyFieldOnChildTable();
                    $keyFieldOnParentModel = $relationParams->query->getKeyFieldOnParentTable();

                    $query = ($relationParams->query->entity)::beforeFind($relationParams->query)
                        ->where([
                            $keyFieldOnChildModel => $relationParams->ids->toArray()
                        ]);

                    if(isset($relationParams->callable)){
                        $query = \call_user_func($relationParams->callable, $query);
                    }

                    $children = $query->fetchAll();
                    //echo "{$this->entity} tiene ".count($children)." hijos de $relationName...\n";
                    
                    
                    //Init relation field on parent objects
                    foreach($result as $parent){
                        if($relationParams->query->returnsMany){
                            $parent->$relationName = new \Ds\Vector();
                        } else {
                            $parent->$relationName = null;
                        }
                    }

                    $keyFieldOnChildren = $relationParams->query->getKeyFieldOnChildrenObjects();

                    if(($dot = strpos($keyFieldOnParentModel, '.')) !== false){
                        $keyFieldOnParentModel = \substr($keyFieldOnParentModel, $dot + 1);
                    }

                    foreach($children as $child){
                        //Assign child to its respective parent
                        foreach($result as $parent){
                            if($parent->$keyFieldOnParentModel == $child->$keyFieldOnChildren){
                                if ($relationParams->query->returnsMany){
                                    $parent->$relationName->push($child);
                                } else if ($parent->$relationName === null){
                                    $parent->$relationName = $child;
                                }
                                break;
                            }
                        }
                    }

                    $relationParams->fetched = true;
                }

            }
        }
    }

    protected function guessTableName($class = null)
    {
        return strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/', '_$0',
                (new \ReflectionClass($class !== null ? $class : $this->entity))->getShortName()
            )
        );
    }
}
