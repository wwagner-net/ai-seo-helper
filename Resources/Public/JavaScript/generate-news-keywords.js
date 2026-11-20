import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import labels from "~labels/ai_seo_helper.backend";

class GenerateNewsKeywords {
    constructor() {
        this.addEventListener = this.addEventListener.bind(this);
        this.addEventListener();
    }

    addEventListener() {
        let executeRequest = this.sendAjaxRequest;
        document.getElementById('generateNewsKeywords').addEventListener("click", function(ev) {
            ev.preventDefault();

            let newsId = parseInt(this.getAttribute('data-news-id'));
            let folderId = parseInt(this.getAttribute('data-folder-id'));
            let fieldName = this.getAttribute('data-field-name');

            executeRequest(newsId, folderId, fieldName);
        });
    }

    /**
     *
     * @param {int} newsId
     * @param {int} folderId
     * @param {string} fieldName
     */
    sendAjaxRequest(newsId, folderId, fieldName) {
        Notification.info(labels.get('AiSeoHelper.notification.generation.start'), labels.get('AiSeoHelper.notification.generation.start.keywords'), 8);
        new AjaxRequest(TYPO3.settings.ajaxUrls['news_keywords_generation'])
            .post(
                { newsId: newsId, folderId: folderId }
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if(responseBody.error) {
                    Notification.error(labels.get('AiSeoHelper.notification.generation.requestError'), responseBody.error);
                } else {
                    document.querySelector('textarea[name="data[tx_news_domain_model_news]['+newsId+']['+fieldName+']"]').value = responseBody.output;
                    Notification.success(labels.get('AiSeoHelper.notification.generation.finish'), labels.get('AiSeoHelper.notification.generation.finish.keywords'), 8);
                }
            })
            .catch((error) => {
                Notification.error(labels.get('AiSeoHelper.notification.generation.error'), error);
            });
    }
}

export default new GenerateNewsKeywords();
