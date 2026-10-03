<?php

declare(strict_types=1);

return [
    'accepted' => ':Attribute moet geaccepteerd worden.',
    'array' => ':Attribute moet een lijst zijn.',
    'boolean' => ':Attribute moet ja of nee zijn.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'date' => ':Attribute moet een geldige datum zijn.',
    'different' => ':Attribute en :other moeten verschillen.',
    'email' => ':Attribute moet een geldig e-mailadres zijn.',
    'enum' => 'De gekozen :attribute is ongeldig.',
    'exists' => 'De gekozen :attribute bestaat niet.',
    'in' => 'De gekozen :attribute is ongeldig.',
    'integer' => ':Attribute moet een geheel getal zijn.',
    'max' => [
        'array' => ':Attribute mag niet meer dan :max items bevatten.',
        'file' => ':Attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':Attribute mag niet groter zijn dan :max.',
        'string' => ':Attribute mag niet meer dan :max tekens bevatten.',
    ],
    'min' => [
        'array' => ':Attribute moet minimaal :min items bevatten.',
        'file' => ':Attribute moet minimaal :min kilobytes zijn.',
        'numeric' => ':Attribute moet minimaal :min zijn.',
        'string' => ':Attribute moet minimaal :min tekens bevatten.',
    ],
    'not_in' => 'De gekozen :attribute is ongeldig.',
    'numeric' => ':Attribute moet een getal zijn.',
    'password' => [
        'letters' => ':Attribute moet minimaal één letter bevatten.',
        'mixed' => ':Attribute moet minimaal één hoofdletter en één kleine letter bevatten.',
        'numbers' => ':Attribute moet minimaal één cijfer bevatten.',
        'symbols' => ':Attribute moet minimaal één leesteken bevatten.',
        'uncompromised' => 'Dit :attribute komt voor in een datalek. Kies een ander :attribute.',
    ],
    'prohibited' => ':Attribute is niet toegestaan.',
    'prohibited_if' => ':Attribute is niet toegestaan als :other :value is.',
    'required' => ':Attribute is verplicht.',
    'required_if' => ':Attribute is verplicht als :other :value is.',
    'required_with' => ':Attribute is verplicht als :values is ingevuld.',
    'required_without' => ':Attribute is verplicht als :values niet is ingevuld.',
    'string' => ':Attribute moet tekst zijn.',
    'unique' => 'Dit :attribute is al in gebruik.',
    'url' => ':Attribute moet een geldige URL zijn.',
    'uuid' => ':Attribute moet een geldige UUID zijn.',

    'attributes' => [
        'email' => 'e-mailadres',
        'name' => 'naam',
        'password' => 'wachtwoord',
        'password_confirmation' => 'wachtwoordbevestiging',
    ],
];
