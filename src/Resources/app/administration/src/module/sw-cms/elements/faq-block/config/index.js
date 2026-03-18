import template from './sw-cms-el-config-deskly-faq-block.html.twig';

const { Component } = Shopware;

Component.register('sw-cms-el-config-deskly-faq-block', {
    template,

    mixins: [
        'cms-element',
    ],

    data() {
        return {
            categories: [],
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('deskly_kb_category');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('deskly-faq-block');
            this.loadCategories();
        },

        async loadCategories() {
            const criteria = new Shopware.Data.Criteria();
            criteria.addSorting(Shopware.Data.Criteria.sort('position', 'ASC'));

            try {
                const result = await this.categoryRepository.search(criteria, Shopware.Context.api);
                this.categories = result;
            } catch {
                this.categories = [];
            }
        },

        onElementUpdate(element) {
            this.$emit('element-update', element);
        },
    },
});
