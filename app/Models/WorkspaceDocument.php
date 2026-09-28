<?php

namespace App\Models;

class WorkspaceDocument extends TenantModel
{
    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }
}
