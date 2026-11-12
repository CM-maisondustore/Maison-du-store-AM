<?php
namespace App\Services;
use App\Models\Contact;
class ContactArchive
{
    public function capture(array $data): Contact
    {
        // Public submissions may create a dossier, never overwrite verified CRM data.
        $values=[
            'name' => trim($data['firstName'].' '.$data['lastName']),
            'phone' => $data['phone'],
            'address' => trim(($data['address'] ?? '').' '.($data['city'] ?? '')),
        ];
        $email=mb_strtolower(trim($data['email'] ?? ''));
        if (!$email) return Contact::create([...$values,'email'=>null]);
        // Un dossier à la corbeille garde son e-mail : il est restauré plutôt que dupliqué.
        $contact=Contact::withTrashed()->firstOrCreate(['email'=>$email],$values);
        if ($contact->trashed()) \App\Services\Trash::restore($contact);
        return $contact;
    }
}
