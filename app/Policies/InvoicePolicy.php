<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return (string) $invoice->site?->user_id === (string) $user->id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return (string) $invoice->site?->user_id === (string) $user->id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return (string) $invoice->site?->user_id === (string) $user->id;
    }
}
