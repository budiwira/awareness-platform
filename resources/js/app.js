import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Wire Laravel flash messages ? toast system
router.on("navigate", (event) => {
  const page = event.detail.page;
  if (page.props.flash) {
    import("./Composables/useToast").then(({ useToast }) => {
      const toast = useToast();
      if (page.props.flash.success) toast.success(page.props.flash.success);
      if (page.props.flash.error) toast.error(page.props.flash.error);
    });
  }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: 'var(--brand)',
    },
});
