<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Foundation\Http\FormRequest;

/** Requires an authorized reviewer and a traceable received transfer. */
final class ConfirmRevolutTagPaymentRequest extends FormRequest
{
    /** Allows only administrators who can manage the Credits economy. */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Requires a transfer reference and explicit verification of amount and currency.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:128', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'received' => ['required', 'accepted'],
        ];
    }
}
