import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'deskly-faq',
    label: 'Deskly FAQ',
    category: 'text',
    component: 'sw-cms-block-deskly-faq',
    previewComponent: 'sw-cms-preview-deskly-faq',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'deskly-faq-block',
    },
});
