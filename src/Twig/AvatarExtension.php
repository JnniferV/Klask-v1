<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

final class AvatarExtension
{
    public const DIR = 'avatars';
    public const VARIANTS = [
        '' => 'page Bienvenue',
        'vaisseau' => 'sidebar élève',
        'petitvaisseau' => 'grille accompagnateur',
    ];
    private const FALLBACK = 'default';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[AsTwigFunction('avatar')]
    public function avatar(?string $pseudo, string $variant = ''): string
    {
        $file = $variant.self::slug((string) $pseudo).'.webp';

        if (null === $pseudo || !is_file($this->path($file))) {
            $file = $variant.self::FALLBACK.'.webp';
        }

        return self::DIR.'/'.$file;
    }

    public function path(string $file): string
    {
        return $this->projectDir.'/public/'.self::DIR.'/'.$file;
    }

    public static function slug(string $value): string
    {
        $ascii = strtr(mb_strtolower($value, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $ascii);
    }
}
