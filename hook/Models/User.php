<?php

namespace TLC\Hook\Models;

class User extends BaseModel
{
    protected array $encryptedFields = ['email'];
    protected string $collectionName = 'users';
}
