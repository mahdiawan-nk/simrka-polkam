<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KnowledgeCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KnowledgeCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KnowledgeCategory');
    }

    public function view(AuthUser $authUser, KnowledgeCategory $knowledgeCategory): bool
    {
        return $authUser->can('View:KnowledgeCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KnowledgeCategory');
    }

    public function update(AuthUser $authUser, KnowledgeCategory $knowledgeCategory): bool
    {
        return $authUser->can('Update:KnowledgeCategory');
    }

    public function delete(AuthUser $authUser, KnowledgeCategory $knowledgeCategory): bool
    {
        return $authUser->can('Delete:KnowledgeCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:KnowledgeCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KnowledgeCategory');
    }
}
