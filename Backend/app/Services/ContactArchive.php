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
        return $email ? Contact::firstOrCreate(['email'=>$email],$values) : Contact::create([...$values,'email'=>null]);
    }
}
