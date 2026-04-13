(function () {
  var compat = flarum.core.compat;
  var appMod = compat['admin/app'] || compat['app'];
  var app = appMod && (appMod.default || appMod);
  var epMod = compat['admin/components/ExtensionPage'];
  var ExtensionPage = epMod && (epMod.default || epMod);
  var usMod = compat['admin/components/UserSelector'];
  var UserSelector = usMod && (usMod.default || usMod);
  var btnMod = compat['common/components/Button'];
  var Button = btnMod && (btnMod.default || btnMod);
  var streamMod = compat['common/utils/Stream'];
  var Stream = streamMod && (streamMod.default || streamMod);
  var etMod = compat['common/utils/extractText'];
  var extractText = etMod && (etMod.default || etMod);

  class AiSettingsPage extends ExtensionPage {
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

      this.loading = false;
    }

    content() {
      var self = this;

      return [
        m('.container', { style: { marginTop: '20px', maxWidth: '800px' } },
          m('.AiSettingsPage',
            m('.Form',
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.ai_user_label'))),
                m(UserSelector, {
                  value: this.aiUserId(),
                  onchange: function (user) { self.aiUserId(user ? user.id() : ''); },
                  placeholder: extractText(app.translator.trans('nopj-ai.admin.settings.ai_user_placeholder')),
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.api_endpoint_label'))),
                m('input.FormControl', {
                  type: 'url',
                  value: this.apiEndpoint(),
                  oninput: function (e) { self.apiEndpoint(e.target.value); },
                  placeholder: 'https://api.openai.com/v1',
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.api_key_label'))),
                m('input.FormControl', {
                  type: 'password',
                  value: this.apiKey(),
                  oninput: function (e) { self.apiKey(e.target.value); },
                  placeholder: 'sk-...',
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.model_label'))),
                m('input.FormControl', {
                  type: 'text',
                  value: this.model(),
                  oninput: function (e) { self.model(e.target.value); },
                  placeholder: 'gpt-3.5-turbo',
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.system_prompt_label'))),
                m('textarea.FormControl', {
                  rows: 5,
                  value: this.systemPrompt(),
                  oninput: function (e) { self.systemPrompt(e.target.value); },
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.max_tokens_label'))),
                m('input.FormControl', {
                  type: 'number',
                  value: this.maxTokens(),
                  oninput: function (e) { self.maxTokens(e.target.value); },
                  min: '1',
                  max: '4096',
                })
              ),
              m('.Form-group',
                m('label', extractText(app.translator.trans('nopj-ai.admin.settings.temperature_label'))),
                m('input.FormControl', {
                  type: 'number',
                  value: this.temperature(),
                  oninput: function (e) { self.temperature(e.target.value); },
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
                  oninput: function (e) { self.contextPostsCount(e.target.value); },
                  min: '1',
                  max: '20',
                })
              ),
              m('.Form-group',
                Button.component(
                  {
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

    onsubmit(e) {
      e.preventDefault();
      this.loading = true;

      var self = this;

      app.request({
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
          },
        },
      })
      .then(function () {
        app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message'));
      })
      .catch(function (error) {
        app.alerts.show({ type: 'error' }, error.message);
      })
      .then(function () {
        self.loading = false;
        m.redraw();
      });
    }
  }

  app.initializers.add('nopj-ai', function () {
    if (app && app.extensionData && AiSettingsPage) {
      app.extensionData
        .for('nopj-ai')
        .registerPage(AiSettingsPage)
        .registerPermission({
          icon: 'fas fa-robot',
          label: 'nopj-ai.admin.permissions.use_ai',
          permission: 'nopj-ai.use_ai',
        }, 'start');
    }
  });
})();
