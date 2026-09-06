<?php

namespace App\Http\Middleware;

/**
 * @deprecated Use RequirePlatformUser::class instead.
 *
 * This alias is maintained for backwards compatibility. The middleware
 * permits Platform Super Admins and Platform Employees (with assigned capabilities).
 */
class RequireSuperAdmin extends RequirePlatformUser
{
}
