<?php

declare(strict_types=1);

namespace WebVision\WvDeepltranslate\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\WvDeepltranslate\Exception\LanguageIsoCodeNotFoundException;
use WebVision\WvDeepltranslate\Exception\LanguageRecordNotFoundException;

class LanguageService
{
    protected DeeplService $deeplService;

    protected array $possibleLangMatches = [
        'deeplTargetLanguage',
        'hreflang',
        'iso-639-1',
    ];

    public function __construct(
        ?DeeplService $deeplService = null
    ) {
        $this->deeplService = $deeplService ?? GeneralUtility::makeInstance(DeeplService::class);
    }

    /**
     * @return array{site: Site, pageUid: int}|null
     */
    public function getCurrentSite(string $tableName, int $currentRecordId): ?array
    {
        if ($tableName === 'pages') {
            $pageId = $currentRecordId;
        } else {
            $currentPageRecord = BackendUtility::getRecord($tableName, $currentRecordId);
            $pageId = (int)$currentPageRecord['pid'];
        }
        try {
            return [
                'site' => GeneralUtility::makeInstance(SiteFinder::class)
                    ->getSiteByPageId($pageId),
                'pageUid' => $pageId,
            ];
        } catch (SiteNotFoundException $e) {
            return null;
        }
    }

    /**
     * @return array{uid: int, title: string, language_isocode: string}
     * @throws LanguageIsoCodeNotFoundException
     */
    public function getSourceLanguage(Site $currentSite): array
    {
        $sourceLanguageRecord = [
            'uid' => $currentSite->getDefaultLanguage()->getLanguageId(),
            'title' => $currentSite->getDefaultLanguage()->getTitle(),
            'language_isocode' => strtoupper($currentSite->getDefaultLanguage()->getTwoLetterIsoCode()),
        ];

        if (!in_array(
            $sourceLanguageRecord['language_isocode'],
            $this->deeplService->apiSupportedLanguages['source']
        )) {
            throw new LanguageIsoCodeNotFoundException(
                sprintf(
                    'No API supported target found for language "%s"',
                    $sourceLanguageRecord['title']
                ),
                1676741965
            );
        }

        return $sourceLanguageRecord;
    }

    /**
     * @return array{uid: int, title: string, language_isocode: string}
     * @throws LanguageRecordNotFoundException
     * @throws LanguageIsoCodeNotFoundException
     */
    public function getTargetLanguage(Site $currentSite, int $languageId): array
    {
        $languages = array_filter($currentSite->getConfiguration()['languages'], function ($value) use ($languageId) {
            if (!is_array($value)) {
                return false;
            }

            if ((int)$value['languageId'] === $languageId) {
                return true;
            }

            return false;
        });

        if (count($languages) === 0) {
            throw new LanguageRecordNotFoundException(
                sprintf(
                    'Language "%d" not found in SiteConfig "%s"',
                    $languageId,
                    $currentSite->getConfiguration()['websiteTitle']
                ),
                1676824459
            );
        }
        $language = reset($languages);
        $languageIsoCode = null;

        foreach ($this->possibleLangMatches as $possibleLangMatch) {
            if (array_key_exists($possibleLangMatch, $language)
                && in_array(
                    strtoupper($language[$possibleLangMatch]),
                    $this->deeplService->apiSupportedLanguages['target']
                )
            ) {
                $languageIsoCode = strtoupper($language[$possibleLangMatch]);
                break;
            }
        }
        if ($languageIsoCode === null) {
            throw new LanguageIsoCodeNotFoundException(
                sprintf(
                    'No API supported target found for language "%s" in site "%s"',
                    $language['title'],
                    $currentSite->getConfiguration()['websiteTitle']
                ),
                1676741837
            );
        }

        return [
            'uid' => $language['languageId'] ?? 0,
            'title' => $language['title'],
            'language_isocode' => $languageIsoCode,
        ];
    }
}
