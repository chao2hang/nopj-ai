import app from 'flarum/forum/app';

app.initializers.add('nopj-ai', () => {
  // Forum frontend - can be extended for AI reply status indicators
  console.log('nopj-ai forum initialized');
});
