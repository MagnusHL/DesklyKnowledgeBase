import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'deskly-faq-block',
    label: 'Deskly FAQ Block',
    component: 'sw-cms-el-deskly-faq-block',
    configComponent: 'sw-cms-el-config-deskly-faq-block',
    previewComponent: 'sw-cms-el-preview-deskly-faq-block',
    defaultConfig: {
        tags: {
            source: 'static',
            value: '',
        },
        categoryId: {
            source: 'static',
            value: '',
        },
        maxItems: {
            source: 'static',
            value: 10,
        },
    },
});
