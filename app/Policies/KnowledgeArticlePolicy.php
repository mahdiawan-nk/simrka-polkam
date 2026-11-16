<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KnowledgeArticle;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KnowledgeArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KnowledgeArticle');
    }

    public function view(AuthUser $authUser, KnowledgeArticle $knowledgeArticle): bool
    {
        return $authUser->can('View:KnowledgeArticle');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KnowledgeArticle');
    }

    public function update(AuthUser $authUser, KnowledgeArticle $knowledgeArticle): bool
    {
        return $authUser->can('Update:KnowledgeArticle');
    }

    public function delete(AuthUser $authUser, KnowledgeArticle $knowledgeArticle): bool
    {
        return $authUser->can('Delete:KnowledgeArticle');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:KnowledgeArticle');
    }

    public function publish(AuthUser $authUser, KnowledgeArticle $knowledgeArticle): bool
    {
        return $authUser->can('Publish:KnowledgeArticle') || $authUser->can('Update:KnowledgeArticle');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KnowledgeArticle');
    }
}
