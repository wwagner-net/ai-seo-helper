<?php

declare(strict_types=1);

namespace Passionweb\AiSeoHelper\Service;

use GuzzleHttp\Exception\GuzzleException;
use Passionweb\AiSeoHelper\Factory\CustomLanguageFactory;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ContentService
{
    private readonly array $extConf;

    private ?array $languages = null;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SiteMatcher $siteMatcher,
        private readonly RequestFactory $requestFactory,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly CustomLanguageFactory $customLanguageFactory,
        ExtensionConfiguration $extensionConfiguration,
    ) {
        $this->extConf = $extensionConfiguration->get('ai_seo_helper');
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     * @throws UnableToLinkToPageException
     */
    public function getContentFromAi(
        ServerRequestInterface $request,
        string $extConfPrompt,
        string $extConfReplaceText = ''
    ): array {
        $parsedBody = $request->getParsedBody();

        if (array_key_exists('newsId', $parsedBody)) {
            $newsId = (int)$parsedBody['newsId'];
            $newsRecord = BackendUtility::getRecord('tx_news_domain_model_news', $newsId, 'uid,sys_language_uid,l10n_parent') ?? [];
            // News links address the default language record and resolve the translation via the site language
            $defaultLanguageNewsId = (int)($newsRecord['l10n_parent'] ?? 0) > 0 ? (int)$newsRecord['l10n_parent'] : $newsId;
            $newsLanguageId = max(0, (int)($newsRecord['sys_language_uid'] ?? 0));
            $siteLanguage = $this->getSiteLanguageFromPageId((int)$parsedBody['folderId'], $newsLanguageId);
            $strippedNewsContent = $this->stripNewsContent(
                $this->fetchContentOfNewsArticle($request, $defaultLanguageNewsId, $siteLanguage->getLanguageId())
            );
            return $this->requestAi($strippedNewsContent, $extConfPrompt, $extConfReplaceText, $siteLanguage->getLocale()->getLanguageCode());
        }

        $page = $this->pageRepository->getPage((int)$parsedBody['pageId']);
        $pageId = (int)$parsedBody['pageId'];
        if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
            $pageId = $page['l10n_parent'];
        }
        $siteLanguage = $this->getSiteLanguageFromPageId($pageId, $page['sys_language_uid']);
        $previewUrl = $this->getPreviewUrl($request, $pageId, $siteLanguage->getLanguageId());
        $languageCode = $siteLanguage->getLocale()->getLanguageCode();
        $strippedPageContent = $this->stripPageContent($this->fetchContentFromUrl($previewUrl));

        if ($this->extConf['useUrlForRequest'] === '1') {
            return $this->requestAi($previewUrl, $extConfPrompt, $extConfReplaceText, $languageCode);
        }
        return $this->requestAi($strippedPageContent, $extConfPrompt, $extConfReplaceText, $languageCode);
    }

    /**
     * @throws GuzzleException
     */
    public function requestAi(string $content, string $extConfPromptPrefix, string $extConfReplaceText, string $languageIsoCode): array
    {
        $languages = $this->getLanguages();
        $jsonContent = [
            'model' => $this->extConf['openAiModel'],
            'temperature' => (float)$this->extConf['openAiTemperature'],
            'top_p' => (float)$this->extConf['openAiTopP'],
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->extConf[$extConfPromptPrefix] . ' in ' . $languages[$languageIsoCode] . ":\n\n" . trim($content) . "\n\n Return at least five suggestions and return the response as array in valid JSON format.",
                ],
            ],
        ];

        $response = $this->requestFactory->request(
            'https://api.openai.com/v1/chat/completions',
            'POST',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->extConf['openAiApiKey'],
                ],
                'json' => $jsonContent,
            ]
        );

        $resJsonBody = $response->getBody()->getContents();
        $resBody = json_decode($resJsonBody, true);
        $metadataResponse = json_decode($resBody['choices'][0]['message']['content'], true);
        if (is_array($metadataResponse) && count($metadataResponse) > 1) {
            return $metadataResponse;
        }
        $key = array_key_first($metadataResponse);
        return $metadataResponse[$key];
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     * @throws UnableToLinkToPageException
     */
    public function getContentForSuggestions(ServerRequestInterface $request, string $type): string
    {
        $suggestions = $this->getContentFromAi($request, 'openAiPromptPrefix' . $type);
        $flatSuggestions = [];
        array_walk_recursive($suggestions, function ($value) use (&$flatSuggestions) {
            $flatSuggestions[] = $value;
        });
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:ai_seo_helper/Resources/Private/Templates/Ajax/Ai/'],
            request: $request
        ));
        $view->assign('suggestions', $flatSuggestions);
        return $view->render('GenerateSuggestions');
    }

    public function checkUseForAdditionalFields(string $type): bool
    {
        if ($type !== 'PageTitle' && $type !== 'MetaDescription') {
            return false;
        }
        return (bool)$this->extConf[lcfirst($type) . 'ForOgAndTwitter'];
    }

    protected function getLanguages(): array
    {
        return $this->languages ??= $this->customLanguageFactory->getCustomLanguages();
    }

    protected function stripPageContent(string $pageContent): string
    {
        if (preg_match('~<body[^>]*>(.*?)</body>~si', $pageContent, $body)) {
            $pageContent = $body[0];
        }
        $pageContent = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $pageContent);
        $pageContent = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $pageContent);
        $pageContent = preg_replace('#<footer(.*?)>(.*?)</footer>#is', '', $pageContent);
        $pageContent = preg_replace('#<nav(.*?)>(.*?)</nav>#is', '', $pageContent);
        return strip_tags($pageContent);
    }

    protected function stripNewsContent(string $newsContent): string
    {
        if (preg_match('~<body[^>]*>(.*?)</body>~si', $newsContent, $body)) {
            $newsContent = $body[0];
        }
        $newsContent = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $newsContent);
        $newsContent = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $newsContent);
        $newsContent = preg_replace('#<header(.*?)>(.*?)</header>#is', '', $newsContent);
        $newsContent = preg_replace('#<footer(.*?)>(.*?)</footer>#is', '', $newsContent);
        $newsContent = preg_replace('#<nav(.*?)>(.*?)</nav>#is', '', $newsContent);

        return strip_tags($newsContent);
    }

    /**
     * @throws UnableToLinkToPageException
     */
    protected function getPreviewUrl(ServerRequestInterface $request, int $pageId, int $pageLanguage, array $additionalQueryParameters = []): string
    {
        $additionalGetVars = '_language=' . $pageLanguage;
        foreach ($additionalQueryParameters as $key => $value) {
            $additionalGetVars .= '&' . $key . '=' . $value;
        }

        $previewUri = PreviewUriBuilder::create($pageId)
            ->withAdditionalQueryParameters($additionalGetVars)
            ->buildUri();

        if ($previewUri === null) {
            throw new UnableToLinkToPageException(LocalizationUtility::translate('ai_seo_helper.backend:AiSeoHelper.unableToLinkToPage', null, [$pageId, $pageLanguage]));
        }
        if ($previewUri->getScheme() === '' || $previewUri->getHost() === '') {
            $previewUri = $previewUri
                ->withScheme($request->getUri()->getScheme())
                ->withHost($request->getUri()->getHost());
        }
        if (count($additionalQueryParameters) > 0) {
            return $previewUri->getScheme() . '://' . $previewUri->getHost() . $previewUri->getPath() . '?' . $previewUri->getQuery();
        }
        return $previewUri->getScheme() . '://' . $previewUri->getHost() . $previewUri->getPath();
    }

    protected function getSiteLanguageFromPageId(int $pageId, int $pageSysLanguageUid): SiteLanguage
    {
        $rootLine = BackendUtility::BEgetRootLine($pageId);
        $site = $this->siteMatcher->matchByPageId($pageId, $rootLine);
        return $site->getLanguageById($pageSysLanguageUid);
    }

    /**
     * @throws Exception
     */
    protected function fetchContentFromUrl(string $previewUrl): string
    {
        try {
            return $this->getContentFromPreviewUrl($previewUrl);
        } catch (GuzzleException) {
            return $this->getContentFromPreviewUrl(rtrim($previewUrl, '/'));
        }
    }

    /**
     * @throws Exception
     */
    public function getContentFromPreviewUrl(string $previewUrl): string
    {
        $response = $this->requestFactory->request($previewUrl);
        $fetchedContent = $response->getBody()->getContents();

        if (empty($fetchedContent)) {
            throw new Exception(LocalizationUtility::translate('ai_seo_helper.backend:AiSeoHelper.fetchContentFailed'));
        }
        return $fetchedContent;
    }

    /**
     * @throws Exception
     */
    protected function fetchContentOfNewsArticle(ServerRequestInterface $request, int $newsId, int $pageLanguage): string
    {
        $additionalQueryParameters = [
            'tx_news_pi1[action]' => 'detail',
            'tx_news_pi1[controller]' => 'News',
            'tx_news_pi1[news]' => $newsId,
        ];

        $previewUrl = $this->getPreviewUrl($request, (int)$this->extConf['singleNewsDisplayPage'], $pageLanguage, $additionalQueryParameters);
        return $this->getContentFromPreviewUrl($previewUrl);
    }
}
