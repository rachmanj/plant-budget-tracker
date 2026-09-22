<?php

namespace App\Support;

use App\Models\ProjectCache;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectContext
{
    public static function allowSwitch(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->project_code_scope === null || $user->project_code_scope === '';
    }

    public static function resolve(Request $request): string
    {
        $user = $request->user();

        $inputCode = $request->input('project_code');
        if (is_string($inputCode) && $inputCode !== '') {
            if (self::isAccessibleProjectCode($user, $inputCode)) {
                return $inputCode;
            }
        }

        $sessionCode = $request->session()->get('current_project');
        if (is_string($sessionCode) && $sessionCode !== '') {
            if (self::isAccessibleProjectCode($user, $sessionCode)) {
                return $sessionCode;
            }
        }

        if ($user !== null && is_string($user->project_code_scope) && $user->project_code_scope !== '') {
            return $user->project_code_scope;
        }

        $activeCode = ProjectCache::query()
            ->where('is_active', true)
            ->orderBy('project_code')
            ->value('project_code');

        if (is_string($activeCode) && $activeCode !== '') {
            return $activeCode;
        }

        $anyCode = ProjectCache::query()
            ->orderBy('project_code')
            ->value('project_code');

        if (is_string($anyCode) && $anyCode !== '') {
            return $anyCode;
        }

        return '';
    }

    public static function isAccessibleProjectCode(?User $user, string $projectCode): bool
    {
        if ($projectCode === 'all') {
            return self::allowSwitch($user);
        }

        if (! ProjectCache::query()->where('project_code', $projectCode)->exists()) {
            return false;
        }

        if ($user === null) {
            return true;
        }

        $scope = $user->project_code_scope;
        if ($scope !== null && $scope !== '') {
            return $scope === $projectCode;
        }

        return true;
    }
}
