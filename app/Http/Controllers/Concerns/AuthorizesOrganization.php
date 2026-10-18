<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesOrganization
{
    /** 403 unless the model belongs to the signed-in user's organization. */
    protected function authorizeOrg($model): void
    {
        abort_unless($model->organization_id === request()->user()->organization_id, 403);
    }
}
