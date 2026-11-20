<?php

declare(strict_types=1);

namespace Passionweb\AiSeoHelper\FormEngine\FieldControl\News;

use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiNewsKeywords extends AbstractNode
{
    public function render(): array
    {
        $resultArray = [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => LocalizationUtility::translate('ai_seo_helper.backend:AiSeoHelper.generation.newsKeywords'),
            'linkAttributes' => [
                'id' => 'generateNewsKeywords',
                'data-news-id' => $this->data['databaseRow']['uid'],
                'data-folder-id' => $this->data['databaseRow']['pid'],
                'data-field-name' => 'keywords'
            ]
        ];

        $resultArray['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@passionweb/ai-seo-helper/generate-news-keywords.js'),
        ];

        return $resultArray;
    }
}
