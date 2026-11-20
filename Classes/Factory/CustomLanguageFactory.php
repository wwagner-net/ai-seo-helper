<?php

declare(strict_types=1);

namespace Passionweb\AiSeoHelper\Factory;

use Passionweb\AiSeoHelper\Domain\Repository\CustomLanguageRepository;

class CustomLanguageFactory
{
    protected array $languages = [
        'en' => 'English',
        'us' => 'English',
        'gb' => 'English',
        'de' => 'German',
        'at' => 'German',
        'ch' => 'German',
        'fr' => 'French',
        'nl' => 'Dutch',
        'be' => 'Belgian',
        'es' => 'Spanish',
        'pl' => 'Polish',
        'cz' => 'Czech',
        'sk' => 'Slovak',
        'si' => 'Slovenian',
        'ro' => 'Romanian',
        'ua' => 'Ukrainian',
        'it' => 'Italian',
        'se' => 'Swedish',
        'no' => 'Norwegian',
        'fi' => 'Finnish',
        'dk' => 'Danish',
        'jp' => 'Japanese',
        'cn' => 'Chinese',
    ];

    public function __construct(
        private readonly CustomLanguageRepository $customLanguageRepository,
    ) {}

    public function getCustomLanguages(): array
    {
        $customLanguageEntries = $this->customLanguageRepository->findAll();
        $customLanguages = [];
        foreach ($customLanguageEntries as $entry) {
            $customLanguages[$entry->getIsoCode()] = $entry->getSpeech();
        }
        return array_merge($this->languages, $customLanguages);
    }
}
