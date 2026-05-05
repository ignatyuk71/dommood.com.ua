import './bootstrap/axios';
import 'bootstrap';
import { createApp } from 'vue';

const mountPoint = document.querySelector('[data-vue-app]');

if (mountPoint) {
    createApp({}).mount(mountPoint);
}
