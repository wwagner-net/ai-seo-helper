<?php

declare(strict_types=1);

namespace Passionweb\AiSeoHelper\FormEngine\FieldControl;

use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiSeoKeywords extends AbstractNode
{
    public function render(): array
    {
        $resultArray = [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => LocalizationUtility::translate('ai_seo_helper.backend:AiSeoHelper.generation.keywords'),
            'linkAttributes' => [
                'id' => 'generateKeywords',
                'data-page-id' => $this->data['databaseRow']['uid'],
                'data-field-name' => 'keywords'
            ]
        ];

        $resultArray['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@passionweb/ai-seo-helper/generate-keywords.js'),
        ];

        return $resultArray;
    }
}
