<?php

namespace App\Http\Controllers\Staff;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController
{
    public function edit(Request $request)
    {
        return view('staff.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]+$/'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
        ]);

        $photo = $request->file('profile_photo');
        unset($data['profile_photo'], $data['remove_profile_photo']);
        if ($photo) {
            $data['profile_photo'] = base64_encode($photo->getContent());
            $data['profile_photo_mime'] = $photo->getMimeType();
        } elseif ($request->boolean('remove_profile_photo')) {
            $data['profile_photo'] = null;
            $data['profile_photo_mime'] = null;
        }

        $user->update($data);
        Audit::record('profil_modifie', $user);

        return redirect()->route('staff.profile.edit')->with('success', 'Votre profil a été enregistré.');
    }

    public function settings(Request $request)
    {
        return view('staff.settings', ['user' => $request->user()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark'])]]);
        $request->user()->update($data);
        Audit::record('preferences_modifiees', $request->user());

        return redirect()->route('staff.settings.edit')->with('success', 'Votre préférence d’affichage a été enregistrée.');
    }
}
