<?php

namespace Bastivan\UniversalApi\Hook\Models;

class Friend extends BaseModel
{
    protected string $collectionName = 'friends';

    public function __construct()
    {
        parent::__construct($this->collectionName);
    }
}
