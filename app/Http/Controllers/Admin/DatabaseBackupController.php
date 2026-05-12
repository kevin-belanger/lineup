<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupGenerator;
use Symfony\Component\HttpFoundation\Response;

class DatabaseBackupController extends Controller
{
    public function download(DatabaseBackupGenerator $backupGenerator): Response
    {
        return response($backupGenerator->generate(), 200, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$backupGenerator->filename().'"',
        ]);
    }
}
