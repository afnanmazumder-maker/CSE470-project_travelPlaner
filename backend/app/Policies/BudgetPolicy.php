<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function manage(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id;
    }
}
