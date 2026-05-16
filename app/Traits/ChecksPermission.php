<?php

namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

trait ChecksPermission
{
    protected string $permissionPrefix = '';

    protected function checkPermission(string $action): void
    {
        $permission = $this->permissionPrefix.'-'.$action;

        if (! Auth::user()?->hasPermission($permission)) {
            throw new AuthorizationException("Unauthorized: {$permission}");
        }
    }

    public function index(): mixed
    {
        $this->checkPermission('read');

        return parent::index();
    }

    public function create(): mixed
    {
        $this->checkPermission('create');

        return parent::create();
    }

    public function store(): mixed
    {
        $this->checkPermission('create');

        return parent::store();
    }

    public function edit(): mixed
    {
        $this->checkPermission('update');

        return parent::edit();
    }

    public function update(): mixed
    {
        $this->checkPermission('update');

        return parent::update();
    }

    public function destroy(): mixed
    {
        $this->checkPermission('delete');

        return parent::destroy();
    }
}
