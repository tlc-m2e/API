<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Hook\Controllers;

class CustomController
{
    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Custom Controller Hook Works!']);
    }
}
