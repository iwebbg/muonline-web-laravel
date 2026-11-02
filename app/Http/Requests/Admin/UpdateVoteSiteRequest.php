<?php

namespace App\Http\Requests\Admin;

use App\Models\VoteSite;
use Illuminate\Validation\Rule;

/**
 * Validates updates while reusing vote-site authorization and reward policy.
 */
final class UpdateVoteSiteRequest extends StoreVoteSiteRequest
{
    /**
     * Returns creation rules with a uniqueness exception for the current site.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $voteSite = $this->route('voteSite');

        if ($voteSite instanceof VoteSite) {
            $rules['slug'] = [
                'required',
                'string',
                'max:80',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('vote_sites', 'slug')->ignore($voteSite),
            ];
        }

        return $rules;
    }
}
