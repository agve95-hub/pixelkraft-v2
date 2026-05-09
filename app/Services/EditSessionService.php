<?php

namespace App\Services;

use App\Models\EditSession;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;

class EditSessionService
{
    public function __construct(
        private GitSyncService $git,
    ) {}

    public function startOrResume(Site $site, Page $page, ?User $user = null): EditSession
    {
        $user ??= auth()->user();

        $existing = EditSession::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->where('started_by', $user?->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $baseCommitSha = null;
        if ($this->git->isCloned($site)) {
            try {
                $baseCommitSha = $this->git->currentCommitSha($site);
            } catch (\Throwable) {
                $baseCommitSha = null;
            }
        }

        return EditSession::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'started_by' => $user?->id,
            'base_commit_sha' => $baseCommitSha,
            'working_branch' => $this->workingBranchName($site, $page, $user),
            'status' => 'active',
            'metadata' => [
                'page_file_path' => $page->file_path,
            ],
            'started_at' => now(),
        ]);
    }

    public function markConflict(EditSession $session, array $metadata = []): void
    {
        $session->close('conflicted', $metadata);
    }

    public function close(EditSession $session, array $metadata = []): void
    {
        $session->close('closed', $metadata);
    }

    private function workingBranchName(Site $site, Page $page, ?User $user): string
    {
        $userToken = $user?->id ? substr((string) $user->id, 0, 8) : 'system';
        $pageToken = substr(md5($page->id), 0, 8);

        return "platform/{$site->slug}/{$userToken}-{$pageToken}";
    }
}
