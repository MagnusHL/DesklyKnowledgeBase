import template from './sw-cms-el-config-deskly-faq-block.html.twig';

const { Component } = Shopware;

Component.register('sw-cms-el-config-deskly-faq-block', {
    template,

    mixins: [
        'cms-element',
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('deskly-faq-block');
        },

        onElementUpdate(element) {
            this.$emit('element-update', element);
        },
    },
});
