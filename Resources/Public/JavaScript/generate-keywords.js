import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import labels from "~labels/ai_seo_helper.backend";

class GenerateKeywords {
    constructor() {
        this.addEventListener = this.addEventListener.bind(this);
        this.addEventListener();
    }

    addEventListener() {
        let executeRequest = this.sendAjaxRequest;
        document.getElementById('generateKeywords').addEventListener("click", function(ev) {
            ev.preventDefault();

            let pageId = parseInt(this.getAttribute('data-page-id'));
            let fieldName = this.getAttribute('data-field-name');

            executeRequest(pageId, fieldName);
        });
    }

    /**
     *
     * @param {int} pageId
     * @param {string} fieldName
     */
    sendAjaxRequest(pageId, fieldName) {
        Notification.info(labels.get('AiSeoHelper.notification.generation.start'), labels.get('AiSeoHelper.notification.generation.start.keywords'), 8);
        new AjaxRequest(TYPO3.settings.ajaxUrls['keywords_generation'])
            .post(
                { pageId: pageId }
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if(responseBody.error) {
                    Notification.error(labels.get('AiSeoHelper.notification.generation.requestError'), responseBody.error);
                } else {
                    document.querySelector('textarea[name="data[pages]['+pageId+']['+fieldName+']"]').value = responseBody.output;
                    Notification.success(labels.get('AiSeoHelper.notification.generation.finish'), labels.get('AiSeoHelper.notification.generation.finish.keywords'), 8);
                }
            })
            .catch((error) => {
                Notification.error(labels.get('AiSeoHelper.notification.generation.error'), error);
            });
    }
}

export default new GenerateKeywords();
