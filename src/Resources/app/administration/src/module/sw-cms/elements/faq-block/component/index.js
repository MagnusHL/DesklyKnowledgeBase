import template from './sw-cms-el-deskly-faq-block.html.twig';

const { Component } = Shopware;

Component.register('sw-cms-el-deskly-faq-block', {
    template,

    mixins: [
        'cms-element',
    ],

    computed: {
        tags() {
            return this.element?.config?.tags?.value || '';
        },

        categoryId() {
            return this.element?.config?.categoryId?.value || '';
        },

        maxItems() {
            return this.element?.config?.maxItems?.value || 10;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('deskly-faq-block');
        },
    },
});
