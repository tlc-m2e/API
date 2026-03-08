<?php

declare(strict_types=1);

namespace TLC\Hook\Controllers;

class CustomController extends BaseController
{
    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Custom Controller Hook Works!']);
    }
}
