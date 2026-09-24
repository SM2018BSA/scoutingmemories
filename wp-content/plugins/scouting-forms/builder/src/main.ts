import { createApp } from 'vue';
import App from './App.vue';
import './style.css';

const mountEl = document.getElementById('sm-builder-app');
if (mountEl) {
  createApp(App).mount(mountEl);
}
