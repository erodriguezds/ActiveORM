<?php

namespace ActiveORM;

interface HydratableInterface
{
    public function hydrate($data);
    public function getHydratableFields();
    public function getIgnoreFields();
}