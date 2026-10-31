import { Controller } from '@hotwired/stimulus';
import { SuggestInput } from '@pumukit/new-admin/suggest';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['login', 'mail'];
    static values = {
        autocompleteUrl: String,
        linkUrl: String,
        target: String,
        preview: String,
        modal: String,
        messages: Object,
    };

    connect() {
        this.suggest = new SuggestInput(this.loginTarget, {
            source: (term, signal) => this.search(term, signal),
            minLength: 2,
            select: (item) => {
                this.mailTarget.value = item.id;
            },
        });
    }

    disconnect() {
        this.suggest?.destroy();
        this.suggest = null;
    }

    async search(term, signal) {
        const url = new URL(this.autocompleteUrlValue, window.location.href);
        url.searchParams.set('term', term);
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
            if (!response.ok) {
                window.no_success(await response.text());

                return [];
            }
            const people = await response.json();

            return people.map((person) => ({ id: person.mail, text: person.cn, description: person.mail, value: person.cn }));
        } catch {
            return [];
        }
    }

    clear() {
        this.mailTarget.value = '';
    }

    use(event) {
        event.preventDefault();
        const mail = this.mailTarget.value;
        if ('' === mail) {
            window.alert(this.messagesValue.selectFirst);

            return;
        }
        $.ajax({ url: this.linkUrlValue, type: 'POST', data: { mail } })
            .done((html) => {
                $(this.targetValue).html(html);
                window.success(this.messagesValue.linked);
                if (this.previewValue) {
                    $('#preview_mm').load(this.previewValue, { _method: 'POST' });
                }
            })
            .fail((xhr) => {
                window.no_success(xhr.responseText);
            });
        $(this.modalValue).modal('hide');
    }
}
