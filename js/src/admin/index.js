import app from 'flarum/admin/app';
import AiSettingsPage from './components/AiSettingsPage';

app.initializers.add('nopj-ai', () => {
  console.log('nopj-ai admin initializer starting');
  
  app.extensionData
    .for('nopj-ai')
    .registerPage(AiSettingsPage)
    .registerPermission({
      icon: 'fas fa-robot',
      label: 'nopj-ai.admin.permissions.use_ai',
      permission: 'nopj-ai.use_ai',
    }, 'start');
    
  console.log('nopj-ai admin initializer complete');
});
