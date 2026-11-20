<?php

declare(strict_types=1);

namespace Passionweb\AiSeoHelper\Service;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class JavaScriptModuleService
{
    /**
     * @return array{javaScriptModules: list<JavaScriptModuleInstruction>}
     */
    public function addModules(): array
    {
        $modules = [
            JavaScriptModuleInstruction::create('@passionweb/ai-seo-helper/Helper/generate-suggestions.js'),
        ];
        if (ExtensionManagementUtility::isLoaded('news')) {
            $modules[] = JavaScriptModuleInstruction::create('@passionweb/ai-seo-helper/Helper/news-generate-suggestions.js');
        }
        return ['javaScriptModules' => $modules];
    }
}
