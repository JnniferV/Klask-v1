<?php

namespace App\Service;

final class Questionnaire
{
    /** @var array<string, array{text: string, zone: string}> */
    public const AFFIRMATIONS = [
        'A' => ['text' => "J'aime créer de mes mains : Je suis manuel, j'aime transformer la matière, cuisiner ou réparer.", 'zone' => 'CRÉATIF'],
        'B' => ['text' => "Je suis rigoureux : J'aime l'ordre, les règles, la précision et quand tout est bien organisé.", 'zone' => 'RIGOUREUX'],
        'C' => ['text' => "J'aime la nouveauté : Je suis curieux des technologies, du digital, de l'info et de l'innovation.", 'zone' => 'NOUVEAUTÉ'],
        'D' => ['text' => "J'aime être en extérieur : J'ai besoin de bouger, d'être dehors et au contact de la nature ou du terrain.", 'zone' => 'EXTÉRIEUR'],
        'E' => ['text' => "J'aime communiquer : J'aime parler, convaincre, expliquer des choses et rencontrer de nouvelles personnes.", 'zone' => 'COMMUNIQUER'],
        'F' => ['text' => "J'aime me sentir utile : J'ai le sens du service, j'aime soigner, aider et m'occuper des autres.", 'zone' => 'UTILE'],
    ];

    /**
     * @param array<string, mixed> $ratings
     *
     * @return array<string, int>
     */
    public function toZoneRatings(array $ratings): array
    {
        $zoneRatings = [];

        foreach (self::AFFIRMATIONS as $letter => $affirmation) {
            $value = isset($ratings[$letter]) ? (int) $ratings[$letter] : 0;

            if ($value < 1 || $value > 6) {
                throw new \InvalidArgumentException('Chaque affirmation doit recevoir une note entre 1 et 6.');
            }

            $zoneRatings[$affirmation['zone']] = $value;
        }

        if (count(array_unique($zoneRatings)) !== count(self::AFFIRMATIONS)) {
            throw new \InvalidArgumentException('Tu ne peux utiliser chaque chiffre (1 à 6) qu\'une seule fois.');
        }

        return $zoneRatings;
    }
}
