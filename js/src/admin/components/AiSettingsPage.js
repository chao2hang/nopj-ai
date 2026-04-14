import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';

export default class AiSettingsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.aiUserId = Stream(app.data.settings['nopj-ai.ai_user_id'] || '');
    this.apiEndpoint = Stream(app.data.settings['nopj-ai.api_endpoint'] || 'https://api.openai.com/v1');
    this.apiKey = Stream(app.data.settings['nopj-ai.api_key'] || '');
    this.model = Stream(app.data.settings['nopj-ai.model'] || 'gpt-3.5-turbo');
    this.systemPrompt = Stream(app.data.settings['nopj-ai.system_prompt'] || 'You are a helpful AI assistant integrated in a Flarum forum. Answer questions concisely and helpfully based on the discussion context provided.');
    this.maxTokens = Stream(app.data.settings['nopj-ai.max_tokens'] || '1024');
    this.temperature = Stream(app.data.settings['nopj-ai.temperature'] || '0.7');
    this.contextPostsCount = Stream(app.data.settings['nopj-ai.context_posts_count'] || '5');
    this.streaming = Stream(app.data.settings['nopj-ai.streaming'] === true || app.data.settings['nopj-ai.streaming'] === '1');

    this.loading = false;
  }

  content() {
    return [
      m('.container', { style: { marginTop: '20px', maxWidth: '800px' } },
        m('.AiSettingsPage',
          m('.Form',
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.ai_user_label'))),
              m('input.FormControl', {
                type: 'text',
                value: this.aiUserId(),
                oninput: (e) => this.aiUserId(e.target.value),
                placeholder: extractText(app.translator.trans('nopj-ai.admin.settings.ai_user_placeholder')),
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.api_endpoint_label'))),
              m('input.FormControl', {
                type: 'url',
                value: this.apiEndpoint(),
                oninput: (e) => this.apiEndpoint(e.target.value),
                placeholder: 'https://api.openai.com/v1',
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.api_key_label'))),
              m('input.FormControl', {
                type: 'password',
                value: this.apiKey(),
                oninput: (e) => this.apiKey(e.target.value),
                placeholder: 'sk-...',
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.model_label'))),
              m('input.FormControl', {
                type: 'text',
                value: this.model(),
                oninput: (e) => this.model(e.target.value),
                placeholder: 'gpt-3.5-turbo',
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.system_prompt_label'))),
              m('textarea.FormControl', {
                rows: 5,
                value: this.systemPrompt(),
                oninput: (e) => this.systemPrompt(e.target.value),
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.max_tokens_label'))),
              m('input.FormControl', {
                type: 'number',
                value: this.maxTokens(),
                oninput: (e) => this.maxTokens(e.target.value),
                min: '1',
                max: '4096',
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.temperature_label'))),
              m('input.FormControl', {
                type: 'number',
                value: this.temperature(),
                oninput: (e) => this.temperature(e.target.value),
                min: '0',
                max: '2',
                step: '0.1',
              })
            ),
            m('.Form-group',
              m('label', extractText(app.translator.trans('nopj-ai.admin.settings.context_posts_count_label'))),
              m('input.FormControl', {
                type: 'number',
                value: this.contextPostsCount(),
                oninput: (e) => this.contextPostsCount(e.target.value),
                min: '1',
                max: '20',
              })
            ),
            m('.Form-group',
              m('label', { className: 'checkbox' },
                m('input', {
                  type: 'checkbox',
                  checked: this.streaming(),
                  onchange: (e) => this.streaming(e.target.checked),
                }),
                extractText(app.translator.trans('nopj-ai.admin.settings.streaming_label'))
              ),
              m('.helpText', extractText(app.translator.trans('nopj-ai.admin.settings.streaming_help')))
            ),
            m('.Form-group',
              m(Button, {
                type: 'submit',
                className: 'Button Button--primary',
                loading: this.loading,
                onclick: this.onsubmit.bind(this),
              },
                app.translator.trans('core.admin.settings.submit_changes_button')
              )
            )
          )
        )
      ),
    ];
  }

  async onsubmit(e) {
    e.preventDefault();
    this.loading = true;

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/nopj-ai/settings',
        body: {
          settings: {
            ai_user_id: this.aiUserId(),
            api_endpoint: this.apiEndpoint(),
            api_key: this.apiKey(),
            model: this.model(),
            system_prompt: this.systemPrompt(),
            max_tokens: this.maxTokens(),
            temperature: this.temperature(),
            context_posts_count: this.contextPostsCount(),
            streaming: this.streaming() ? '1' : '0',
          },
        },
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message'));
    } catch (error) {
      app.alerts.show({ type: 'error' }, error.message);
    } finally {
      this.loading = false;
    }
  }
}
