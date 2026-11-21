<?php

namespace App\Livewire\Admin\TVClub\Social;

use App\Exceptions\TVClubSocialException;
use App\Models\TVClubSocialAccount;
use App\Services\TVClubSocialService;
use App\Support\TVClubSocialRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class AccountForm extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public ?int $accountId = null;

    #[Locked]
    public int $version = 0;

    public string $platform = 'tiktok';

    public string $accountKey = '';

    public string $handle = '';

    public string $displayName = '';

    public string $profileUrl = '';

    public function mount(?TVClubSocialAccount $account = null): void
    {
        $this->authorize('manageAccounts', TVClubSocialAccount::class);
        if ($account?->exists) {
            $this->accountId = $account->id;
            $this->version = $account->lock_version;
            $this->platform = $account->platform;
            $this->accountKey = $account->account_key;
            $this->handle = $account->handle;
            $this->displayName = $account->display_name;
            $this->profileUrl = $account->profile_url ?? '';
        }
    }

    public function save(TVClubSocialService $service): void
    {
        $this->authorize('manageAccounts', TVClubSocialAccount::class);
        try {
            if ($this->accountId === null) {
                $service->createAccount(auth()->user(), $this->platform, $this->accountKey, $this->handle, $this->displayName, $this->profileUrl);
            } else {
                $service->updateAccount(auth()->user(), $this->accountId, $this->version, $this->handle, $this->displayName, $this->profileUrl);
            }
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Akun disimpan.');
        $this->redirectRoute('admin.tvclub.social-accounts.index');
    }

    public function archive(TVClubSocialService $service): void
    {
        $this->authorize('manageAccounts', TVClubSocialAccount::class);
        abort_if($this->accountId === null, 404);
        try {
            $service->archiveAccount(auth()->user(), $this->accountId, $this->version);
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Akun diarsipkan.');
        $this->redirectRoute('admin.tvclub.social-accounts.index');
    }

    public function render()
    {
        $this->authorize('manageAccounts', TVClubSocialAccount::class);
        $account = $this->accountId === null ? null : TVClubSocialAccount::findOrFail($this->accountId);

        return view('livewire.admin.tvclub.social.account-form', ['account' => $account, 'platforms' => array_keys(TVClubSocialRules::TYPES)])
            ->title('Akun Media Sosial');
    }
}
